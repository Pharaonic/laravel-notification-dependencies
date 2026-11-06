<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Feature;

use Illuminate\Notifications\DatabaseNotification;
use InvalidArgumentException;
use Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependentNotifications;
use Pharaonic\Laravel\NotificationDependencies\Models\NotificationDependency;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Comment;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\DependentNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\PlainNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Order;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\User;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\UuidModel;
use Pharaonic\Laravel\NotificationDependencies\Tests\TestCase;

class RelationsTest extends TestCase
{
    public function test_a_model_without_the_trait_cannot_be_a_dependency(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('uses '.HasDependentNotifications::class);

        (new DependentNotification)->dependsOn(User::create());
    }

    public function test_a_declared_dependency_without_the_trait_removes_the_stored_notification(): void
    {
        $user = User::create();

        try {
            $user->notify(new DependentNotification([User::create()]));
            $this->fail('A model without the trait was accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString(HasDependentNotifications::class, $e->getMessage());
        }

        $this->assertSame(0, $this->notificationCount());
    }

    public function test_dependent_notifications_returns_the_stored_rows_of_every_recipient(): void
    {
        $alice = User::create();
        $bob = User::create();
        $order = Order::create();
        $other = Order::create();

        $alice->notify((new DependentNotification)->dependsOn($order));
        $bob->notify((new DependentNotification)->dependsOn($order, Comment::create()));
        $alice->notify((new DependentNotification)->dependsOn($other));
        $alice->notify(new PlainNotification);

        $notifications = $order->dependentNotifications()->get();

        $this->assertCount(2, $notifications);
        $this->assertContainsOnlyInstancesOf(DatabaseNotification::class, $notifications);
        $this->assertEqualsCanonicalizing(
            [$alice->getKey(), $bob->getKey()],
            $notifications->pluck('notifiable_id')->map(function ($id) {
                return (int) $id;
            })->all()
        );
        $this->assertCount(1, $other->dependentNotifications);
    }

    public function test_dependent_notifications_can_be_eager_loaded(): void
    {
        $user = User::create();
        [$first, $second] = [Order::create(), Order::create()];
        $user->notify((new DependentNotification)->dependsOn($first, $second));
        $user->notify((new DependentNotification)->dependsOn($second));

        $orders = Order::query()->with('dependentNotifications')->orderBy('id')->get();

        $this->assertSame([1, 2], $orders->map(function (Order $order) {
            return $order->dependentNotifications->count();
        })->all());
    }

    public function test_has_where_has_and_with_count(): void
    {
        $user = User::create();
        [$first, $second, $none] = [Order::create(), Order::create(), Order::create()];
        $user->notify((new DependentNotification)->dependsOn($first, $second));
        $user->notify((new DependentNotification)->dependsOn($second));

        $this->assertSame([$first->getKey(), $second->getKey()], Order::query()->has('dependentNotifications')->orderBy('id')->pluck('id')->all());
        $this->assertSame([$second->getKey()], Order::query()->has('dependentNotifications', '>', 1)->pluck('id')->all());
        $this->assertSame([$none->getKey()], Order::query()->doesntHave('dependentNotifications')->pluck('id')->all());
        $onlySecond = $second->dependentNotifications()->get()->first(function ($notification) use ($first) {
            return ! $first->dependentNotifications->contains($notification);
        });
        $this->assertNotNull($onlySecond);
        $this->assertSame([$second->getKey()], Order::query()->whereHas('dependentNotifications', function ($query) use ($onlySecond) {
            $query->where('notifications.id', $onlySecond->getKey());
        })->pluck('id')->all());
        $this->assertSame([1, 2, 0], Order::query()->withCount('dependentNotifications')->orderBy('id')->get()->map(function (Order $order) {
            return (int) $order->getAttribute('dependent_notifications_count');
        })->all());
    }

    public function test_where_has_with_uuid_keys(): void
    {
        $user = User::create();
        [$with, $without] = [UuidModel::create(), UuidModel::create()];
        $user->notify((new DependentNotification)->dependsOn($with));

        $this->assertSame([$with->getKey()], UuidModel::query()->whereHas('dependentNotifications')->pluck('id')->all());
        $this->assertNotSame($with->getKey(), $without->getKey());
    }

    public function test_dependent_notifications_with_string_keys(): void
    {
        $user = User::create();
        $model = UuidModel::create();
        $user->notify((new DependentNotification)->dependsOn($model));

        $this->assertCount(1, $model->dependentNotifications);
    }

    public function test_the_dependency_model_reaches_the_notification_and_the_model(): void
    {
        $user = User::create();
        $order = Order::create();
        $user->notify((new DependentNotification)->dependsOn($order));

        $link = NotificationDependency::query()->firstOrFail();

        $this->assertSame('notification_dependencies', $link->getTable());
        $this->assertInstanceOf(DatabaseNotification::class, $link->notification);
        $this->assertSame($user->notifications()->value('id'), $link->notification->getKey());
        $this->assertInstanceOf(Order::class, $link->dependable);
        $this->assertTrue($link->dependable->is($order));
    }

    public function test_the_where_dependable_scope_returns_the_links_of_one_model(): void
    {
        $user = User::create();
        $order = Order::create();
        $comment = Comment::create();
        $user->notify((new DependentNotification)->dependsOn($order, $comment));
        $user->notify((new DependentNotification)->dependsOn($order));

        $links = NotificationDependency::whereDependable($order)->with('notification')->get();

        $this->assertCount(2, $links);
        $this->assertSame([Order::class, Order::class], $links->pluck('dependable_type')->all());
        $this->assertContainsOnlyInstancesOf(DatabaseNotification::class, $links->pluck('notification'));
        $this->assertCount(1, NotificationDependency::whereDependable($comment)->get());
        $this->assertCount(0, NotificationDependency::whereDependable(new Order)->get());
    }

    public function test_the_dependency_model_fills_only_its_link_columns(): void
    {
        $model = new NotificationDependency;

        $this->assertSame(['notification_id', 'dependable_type', 'dependable_id'], $model->getFillable());
        $this->assertFalse($model->isFillable('id'));
    }
}
