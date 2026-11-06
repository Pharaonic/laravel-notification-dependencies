<?php

use Illuminate\Support\Facades\Schema;
use Pharaonic\Laravel\NotificationDependencies\Models\NotificationDependency;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Comment;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\DependentNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Order;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\User;

beforeEach(function () {
    config(['pharaonic.notification-dependencies.table' => 'custom_dependencies']);

    // The table name is read by the migration: migrate again with it.
    $this->freshDatabase();
});

test('the migration and every query use the configured table', function () {
    $this->assertTrue(Schema::hasTable('custom_dependencies'));
    $this->assertFalse(Schema::hasTable(NotificationDependency::DEFAULT_TABLE));
    $this->assertSame('custom_dependencies', (new NotificationDependency)->getTable());

    $user = User::create();
    $order = Order::create();
    $comment = Comment::create();
    $user->notify((new DependentNotification)->dependsOn($order, $comment));

    $this->assertSame(2, $this->dependencyCount());
    $this->assertCount(1, $order->dependentNotifications);

    $comment->delete();

    $this->assertSame(0, $this->notificationCount());
    $this->assertSame(0, $this->dependencyCount());
});

test('an empty setting falls back to the default table', function () {
    config(['pharaonic.notification-dependencies.table' => '']);

    $this->assertSame(NotificationDependency::DEFAULT_TABLE, (new NotificationDependency)->getTable());
});
