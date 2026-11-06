<?php

use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\DependentNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\QueuedNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\SoftOrder;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\User;

test('a soft delete deletes the notification by default', function () {
    $this->assertTrue(config('pharaonic.notification-dependencies.delete_on_soft_delete'));

    $user = User::create();
    $order = SoftOrder::create();
    $user->notify((new DependentNotification)->dependsOn($order));

    $order->delete();

    $this->assertTrue($order->trashed());
    $this->assertSame(0, $this->notificationCount());
    $this->assertSame(0, $this->dependencyCount());
});

test('a soft delete keeps the notification until force delete when disabled', function () {
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
});

test('force deleting a model that was never soft deleted', function () {
    config(['pharaonic.notification-dependencies.delete_on_soft_delete' => false]);

    $user = User::create();
    $order = SoftOrder::create();
    $user->notify((new DependentNotification)->dependsOn($order));

    $order->forceDelete();

    $this->assertSame(0, $this->notificationCount());
});

test('a soft deleted model can be a dependency when soft deletes do not invalidate', function () {
    config(['pharaonic.notification-dependencies.delete_on_soft_delete' => false]);

    $user = User::create();
    $order = SoftOrder::create();
    $order->delete();

    $user->notify(new QueuedNotification($order));

    $this->assertSame(1, $this->notificationCount());
    $this->assertSame(1, $this->dependencyCount());
});

test('a soft deleted dependency discards the notification by default', function () {
    $user = User::create();
    $order = SoftOrder::create();
    $order->delete();

    $user->notify(new QueuedNotification($order));

    $this->assertSame(0, $this->notificationCount());
    $this->assertSame(0, $this->dependencyCount());
});
