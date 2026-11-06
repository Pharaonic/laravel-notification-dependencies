<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Feature;

use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\DependentNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\QueuedNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\SoftOrder;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\User;
use Pharaonic\Laravel\NotificationDependencies\Tests\TestCase;

class SoftDeleteTest extends TestCase
{
    public function test_a_soft_delete_deletes_the_notification_by_default(): void
    {
        $this->assertTrue(config('pharaonic.notification-dependencies.delete_on_soft_delete'));

        $user = User::create();
        $order = SoftOrder::create();
        $user->notify((new DependentNotification)->dependsOn($order));

        $order->delete();

        $this->assertTrue($order->trashed());
        $this->assertSame(0, $this->notificationCount());
        $this->assertSame(0, $this->dependencyCount());
    }

    public function test_a_soft_delete_keeps_the_notification_until_force_delete_when_disabled(): void
    {
        config(['pharaonic.notification-dependencies.delete_on_soft_delete' => false]);

        $user = User::create();
        $order = SoftOrder::create();
        $user->notify((new DependentNotification)->dependsOn($order));

        $order->delete();

        $this->assertTrue($order->trashed());
        $this->assertSame(1, $this->notificationCount());

        $order->restore();
        $order->delete();
        $this->assertSame(1, $this->notificationCount());

        $order->forceDelete();

        $this->assertSame(0, $this->notificationCount());
        $this->assertSame(0, $this->dependencyCount());
    }

    public function test_force_deleting_a_model_that_was_never_soft_deleted(): void
    {
        config(['pharaonic.notification-dependencies.delete_on_soft_delete' => false]);

        $user = User::create();
        $order = SoftOrder::create();
        $user->notify((new DependentNotification)->dependsOn($order));

        $order->forceDelete();

        $this->assertSame(0, $this->notificationCount());
    }

    public function test_a_soft_deleted_model_can_be_a_dependency_when_soft_deletes_do_not_invalidate(): void
    {
        config(['pharaonic.notification-dependencies.delete_on_soft_delete' => false]);

        $user = User::create();
        $order = SoftOrder::create();
        $order->delete();

        $user->notify(new QueuedNotification($order));

        $this->assertSame(1, $this->notificationCount());
        $this->assertSame(1, $this->dependencyCount());
    }

    public function test_a_soft_deleted_dependency_discards_the_notification_by_default(): void
    {
        $user = User::create();
        $order = SoftOrder::create();
        $order->delete();

        $user->notify(new QueuedNotification($order));

        $this->assertSame(0, $this->notificationCount());
        $this->assertSame(0, $this->dependencyCount());
    }
}
