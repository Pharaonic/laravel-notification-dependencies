<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Feature;

use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Comment;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\DependentNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\OrderCommented;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\OrderUpdated;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Order;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Product;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\User;
use Pharaonic\Laravel\NotificationDependencies\Tests\TestCase;

class DependsOnTest extends TestCase
{
    public function test_depends_on_returns_the_same_notification(): void
    {
        $notification = new DependentNotification;

        $this->assertSame($notification, $notification->dependsOn(Order::create()));
    }

    public function test_depends_on_one_model(): void
    {
        $order = Order::create();

        User::create()->notify((new DependentNotification)->dependsOn($order));

        $this->assertSame([[Order::class, (string) $order->getKey()]], $this->links());
    }

    public function test_depends_on_several_models_in_one_call(): void
    {
        $order = Order::create();
        $comment = Comment::create();

        User::create()->notify((new DependentNotification)->dependsOn($order, $comment));

        $this->assertSame([
            [Order::class, (string) $order->getKey()],
            [Comment::class, (string) $comment->getKey()],
        ], $this->links());
    }

    public function test_chained_calls_add_to_the_set(): void
    {
        $order = Order::create();
        $comment = Comment::create();
        $product = Product::create();

        $notification = (new DependentNotification)
            ->dependsOn($order)
            ->dependsOn($comment, $product);

        User::create()->notify($notification);

        $this->assertSame([Order::class, Comment::class, Product::class], array_column($this->links(), 0));
    }

    public function test_duplicates_are_ignored_within_and_across_calls(): void
    {
        $order = Order::create();
        $comment = Comment::create();

        $notification = (new DependentNotification)
            ->dependsOn($order, $order, Order::find($order->getKey()))
            ->dependsOn($comment, $order);

        User::create()->notify($notification);

        $this->assertSame([Order::class, Comment::class], array_column($this->links(), 0));
    }

    public function test_declared_and_added_dependencies_merge_without_duplicates(): void
    {
        $order = Order::create();
        $comment = Comment::create();

        User::create()->notify((new DependentNotification([$order]))->dependsOn($comment, $order));

        $this->assertSame([
            [Order::class, (string) $order->getKey()],
            [Comment::class, (string) $comment->getKey()],
        ], $this->links());
    }

    public function test_depends_on_without_models_does_nothing(): void
    {
        $notification = new DependentNotification;

        $this->assertSame($notification, $notification->dependsOn());

        User::create()->notify($notification);

        $this->assertSame(1, $this->notificationCount());
        $this->assertSame(0, $this->dependencyCount());
    }

    public function test_dependencies_defaults_to_an_empty_array(): void
    {
        $this->assertSame([], (new OrderUpdated(Order::create()))->dependencies());
    }

    public function test_dependencies_declared_in_the_class(): void
    {
        $order = Order::create();
        $comment = Comment::create();

        User::create()->notify(new OrderCommented($order, $comment));

        $this->assertSame([Order::class, Comment::class], array_column($this->links(), 0));
    }

    public function test_depends_on_inside_the_constructor(): void
    {
        $order = Order::create();
        $comment = Comment::create();
        $product = Product::create();

        User::create()->notify(new OrderUpdated($order, [$comment, $product]));

        $this->assertSame([Order::class, Comment::class, Product::class], array_column($this->links(), 0));
    }

    public function test_an_unsaved_model_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('cannot depend on an unsaved ['.Order::class.'] model');

        (new DependentNotification)->dependsOn(new Order);
    }

    public function test_a_deleted_model_is_rejected(): void
    {
        $order = Order::create();
        $order->delete();

        $this->expectException(InvalidArgumentException::class);

        (new DependentNotification)->dependsOn($order);
    }

    public function test_a_model_without_a_key_is_rejected(): void
    {
        $order = new Order;
        $order->exists = true;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('integer or non-empty string primary key');

        (new DependentNotification)->dependsOn($order);
    }

    public function test_a_rejected_model_leaves_the_set_unchanged(): void
    {
        $order = Order::create();
        $notification = (new DependentNotification)->dependsOn($order);

        try {
            $notification->dependsOn(new Comment);
            $this->fail('An unsaved model was accepted.');
        } catch (InvalidArgumentException $e) {
            //
        }

        User::create()->notify($notification);

        $this->assertSame([Order::class], array_column($this->links(), 0));
    }

    /**
     * @return array<int, array{0: string, 1: string}>
     */
    private function links(): array
    {
        return DB::table($this->dependencyTable())
            ->orderBy('id')
            ->get(['dependable_type', 'dependable_id'])
            ->map(static function ($row): array {
                return [(string) $row->dependable_type, (string) $row->dependable_id];
            })
            ->all();
    }
}
