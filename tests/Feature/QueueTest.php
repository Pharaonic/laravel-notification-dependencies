<?php

use Illuminate\Support\Facades\DB;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Comment;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\QueuedNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\SerializingQueuedNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Order;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\User;

/**
 * Notifications go through the database queue and a real queue:work run,
 * so every one is serialized, stored, and unserialized before delivery.
 */
beforeEach(function () {
    config(['queue.default' => 'database']);
});

test('dependency identities survive the queue and are linked on delivery', function () {
    $user = User::create();
    $order = Order::create();
    $comment = Comment::create();

    $user->notify(new QueuedNotification($order, $comment));

    $this->assertSame(1, DB::table('jobs')->count());
    $this->assertSame(0, $this->notificationCount());
    $payload = json_decode((string) DB::table('jobs')->value('payload'), true);
    $command = (string) $payload['data']['command'];
    $this->assertStringContainsString('notificationDependencies', $command);
    $this->assertStringNotContainsString(Order::class.'"', str_replace('s:'.strlen(Order::class).':"'.Order::class.'"', '', $command), 'No Order model is serialized.');

    workQueue();

    $this->assertSame(1, $this->notificationCount());
    $this->assertSame(2, $this->dependencyCount());

    $comment->delete();

    $this->assertSame(0, $this->notificationCount());
});

test('a dependency deleted while queued leaves no notification', function () {
    $user = User::create();
    $order = Order::create();
    $comment = Comment::create();

    $user->notify(new QueuedNotification($order, $comment));
    $comment->delete();

    workQueue();

    $this->assertSame(0, DB::table('jobs')->count());
    $this->assertSame(0, DB::table('failed_jobs')->count());
    $this->assertSame(0, $this->notificationCount());
    $this->assertSame(0, $this->dependencyCount());
});

test('a serializes models notification links its declared dependencies', function () {
    $user = User::create();
    $order = Order::create();

    $user->notify(new SerializingQueuedNotification($order));
    workQueue();

    $this->assertSame(1, $this->notificationCount());
    $this->assertSame(1, $this->dependencyCount());
});

test('a serializes models notification whose model is gone is never stored', function () {
    $user = User::create();
    $order = Order::create();

    $user->notify(new SerializingQueuedNotification($order));
    $order->delete();

    workQueue();

    $this->assertSame(1, DB::table('failed_jobs')->count(), 'SerializesModels fails the job when its model is missing.');
    $this->assertSame(0, $this->notificationCount());
    $this->assertSame(0, $this->dependencyCount());
});

test('each queued recipient gets its own links', function () {
    $alice = User::create();
    $bob = User::create();
    $order = Order::create();

    \Illuminate\Support\Facades\Notification::send([$alice, $bob], new QueuedNotification($order));

    workQueue();
    workQueue();

    $this->assertSame(2, $this->notificationCount());
    $this->assertSame(2, $this->dependencyCount());

    $order->delete();

    $this->assertSame(0, $this->notificationCount());
});
