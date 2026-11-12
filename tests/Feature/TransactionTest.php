<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Feature;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Comment;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\DependentNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\PlainNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Order;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\User;
use Pharaonic\Laravel\NotificationDependencies\Tests\TestCase;
use RuntimeException;

class TransactionTest extends TestCase
{
    public function test_a_rolled_back_outer_transaction_leaves_neither_notification_nor_links(): void
    {
        $user = User::create();
        $order = Order::create();

        DB::beginTransaction();
        $user->notify((new DependentNotification)->dependsOn($order, Comment::create()));
        $this->assertSame(2, $this->dependencyCount());
        DB::rollBack();

        $this->assertSame(0, $this->notificationCount());
        $this->assertSame(0, $this->dependencyCount());
    }

    public function test_registration_and_cleanup_run_inside_an_outer_transaction(): void
    {
        $user = User::create();
        $order = Order::create();

        DB::transaction(function () use ($user, $order) {
            $user->notify((new DependentNotification)->dependsOn($order));
            $this->assertSame(1, DB::transactionLevel());
        });

        $this->assertSame(1, $this->dependencyCount());

        DB::beginTransaction();
        $order->delete();
        $this->assertSame(0, $this->notificationCount());
        DB::rollBack();

        $this->assertSame(1, $this->notificationCount(), 'Rolling back the delete brings the notification back with it.');
        $this->assertSame(1, $this->dependencyCount());
    }

    public function test_a_failure_while_linking_removes_the_new_notification_and_rethrows(): void
    {
        $user = User::create();
        $user->notify(new PlainNotification);
        $this->failOnFirstLinkInsert();

        try {
            $user->notify((new DependentNotification)->dependsOn(Order::create(), Comment::create()));
            $this->fail('The failure was swallowed.');
        } catch (RuntimeException $e) {
            $this->assertSame('Injected failure.', $e->getMessage());
        }

        $this->assertSame([PlainNotification::class], DB::table('notifications')->pluck('type')->all());
        $this->assertSame(0, $this->dependencyCount());
    }

    public function test_a_failure_inside_an_outer_transaction_still_compensates(): void
    {
        $user = User::create();
        $this->failOnFirstLinkInsert();

        DB::beginTransaction();

        try {
            $user->notify((new DependentNotification)->dependsOn(Order::create()));
            $this->fail('The failure was swallowed.');
        } catch (RuntimeException $e) {
            //
        }

        $this->assertSame(1, DB::transactionLevel());
        $this->assertSame(0, $this->notificationCount());
        $this->assertSame(0, $this->dependencyCount());

        DB::commit();

        $this->assertSame(0, $this->notificationCount());
    }

    public function test_a_dependency_deleted_before_delivery_discards_the_new_notification(): void
    {
        $user = User::create();
        $order = Order::create();
        $comment = Comment::create();
        $notification = (new DependentNotification)->dependsOn($order, $comment);

        Comment::query()->whereKey($comment->getKey())->delete();

        $user->notify($notification);

        $this->assertSame(0, $this->notificationCount());
        $this->assertSame(0, $this->dependencyCount());
    }

    public function test_a_queued_reference_to_a_removed_class_counts_as_a_deleted_dependency(): void
    {
        $notification = (new DependentNotification)->dependsOn(Order::create());
        $serialized = str_replace(
            's:'.strlen(Order::class).':"'.Order::class.'";s:',
            's:'.strlen(Order::class.'Gone').':"'.Order::class.'Gone";s:',
            serialize($notification)
        );
        $restored = unserialize($serialized);
        $this->assertInstanceOf(DependentNotification::class, $restored);

        // No exception: a retried queued job would send its other channels again.
        User::create()->notify($restored);

        $this->assertSame(0, $this->notificationCount());
        $this->assertSame(0, $this->dependencyCount());
    }

    public function test_the_dependency_rows_are_read_with_a_lock(): void
    {
        $queries = [];
        DB::listen(function (QueryExecuted $query) use (&$queries) {
            $queries[] = strtolower($query->sql);
        });

        User::create()->notify((new DependentNotification)->dependsOn(Order::create()));

        $check = array_values(array_filter($queries, function (string $sql) {
            return Str::startsWith($sql, 'select') && Str::contains($sql, 'orders');
        }));

        // The check before sending, then the one after linking.
        $this->assertCount(2, $check);
        $this->assertStringNotContainsString('for update', $check[0]);

        if (DB::connection()->getDriverName() !== 'sqlite') {
            // A concurrent deletion waits for the links to be committed.
            $this->assertStringContainsString('for update', $check[1]);
        }
    }

    private function failOnFirstLinkInsert(): void
    {
        $failed = false;

        DB::listen(function (QueryExecuted $query) use (&$failed) {
            if (! $failed && Str::startsWith(strtolower($query->sql), 'insert') && Str::contains($query->sql, $this->dependencyTable())) {
                $failed = true;

                throw new RuntimeException('Injected failure.');
            }
        });
    }
}
