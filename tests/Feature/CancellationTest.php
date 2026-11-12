<?php

use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Channels\RecordingChannel;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Comment;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\MultiChannelNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\PlainNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Order;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\SoftOrder;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\User;

/**
 * A notification with a deleted dependency is not sent on any channel.
 */
beforeEach(function () {
    RecordingChannel::$sent = [];
});

test('every channel is sent while the dependencies exist', function () {
    $user = User::create();

    $user->notify(new MultiChannelNotification(Order::create(), Comment::create()));

    $this->assertSame([MultiChannelNotification::class], RecordingChannel::$sent);
    $this->assertSame(1, $this->notificationCount());
    $this->assertSame(2, $this->dependencyCount());
});

test('a dependency deleted while queued cancels every channel', function () {
    config(['queue.default' => 'database']);

    $user = User::create();
    $order = Order::create();
    $comment = Comment::create();

    $user->notify(new MultiChannelNotification($order, $comment));
    $this->assertSame(2, DB::table('jobs')->count(), 'One job per channel.');

    $comment->delete();
    workQueue();
    workQueue();

    $this->assertSame([], RecordingChannel::$sent);
    $this->assertSame(0, DB::table('jobs')->count());
    $this->assertSame(0, DB::table('failed_jobs')->count());
    $this->assertSame(0, $this->notificationCount());
    $this->assertSame(0, $this->dependencyCount());
});

test('a dependency row deleted without events cancels every channel', function () {
    $user = User::create();
    $order = Order::create();

    DB::table('orders')->where('id', $order->getKey())->delete();
    $user->notify(new MultiChannelNotification($order));

    $this->assertSame([], RecordingChannel::$sent);
    $this->assertSame(0, $this->notificationCount());
    $this->assertSame(0, $this->dependencyCount());
});

test('a soft deleted dependency cancels every channel by default', function () {
    $user = User::create();
    $order = SoftOrder::create();
    $notification = new MultiChannelNotification($order);

    $order->delete();
    $user->notify($notification);

    $this->assertSame([], RecordingChannel::$sent);
    $this->assertSame(0, $this->notificationCount());
});

test('a soft deleted dependency is sent when soft deletes are kept', function () {
    config(['pharaonic.notification-dependencies.delete_on_soft_delete' => false]);

    $user = User::create();
    $order = SoftOrder::create();
    $notification = new MultiChannelNotification($order);

    $order->delete();
    $user->notify($notification);

    $this->assertSame([MultiChannelNotification::class], RecordingChannel::$sent);
    $this->assertSame(1, $this->notificationCount());
});

test('other sending listeners still run when the dependencies exist', function () {
    $seen = [];

    Event::listen(NotificationSending::class, function (NotificationSending $event) use (&$seen) {
        $seen[] = $event->channel;
    });

    User::create()->notify(new MultiChannelNotification(Order::create()));

    $this->assertSame([RecordingChannel::class, 'database'], $seen);
    $this->assertSame([MultiChannelNotification::class], RecordingChannel::$sent);
});

test('a notification without the trait is never checked', function () {
    DB::enableQueryLog();

    User::create()->notify(new PlainNotification);

    $queries = array_column(DB::getQueryLog(), 'query');

    $this->assertSame(1, $this->notificationCount());
    $this->assertCount(2, $queries, 'The user and the notification inserts only.');
});
