<?php

use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Pharaonic\Laravel\NotificationDependencies\Listeners\RegisterNotificationDependencies;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Comment;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\DependentNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\PlainNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Order;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Product;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\User;

test('one dependency creates one link to the stored row', function () {
    $user = User::create();
    $order = Order::create();

    $user->notify((new DependentNotification)->dependsOn($order));

    $stored = $user->notifications()->firstOrFail();
    $link = DB::table($this->dependencyTable())->first();

    $this->assertSame(1, $this->dependencyCount());
    $this->assertNotNull($link);
    $this->assertSame($stored->getKey(), $link->notification_id);
    $this->assertSame(Order::class, $link->dependable_type);
    $this->assertSame((string) $order->getKey(), $link->dependable_id);
});

test('three dependencies create three links', function () {
    User::create()->notify((new DependentNotification)->dependsOn(Order::create(), Comment::create(), Product::create()));

    $this->assertSame(3, $this->dependencyCount());
});

test('the stored notification is left as laravel wrote it', function () {
    $user = User::create();

    $user->notify(new PlainNotification);
    $user->notify((new DependentNotification)->dependsOn(Order::create()));

    $notifications = $user->notifications()->orderBy('type')->get();
    $dependent = $notifications->firstWhere('type', DependentNotification::class);

    $this->assertCount(2, $notifications);
    $this->assertInstanceOf(DatabaseNotification::class, $dependent);
    $this->assertSame(['message' => 'Your order received a comment.'], $dependent->data);
    $this->assertNull($dependent->read_at);
    $this->assertSame(User::class, $dependent->notifiable_type);
    $this->assertEquals($user->getKey(), $dependent->notifiable_id);
    $this->assertSame(['id', 'type', 'notifiable_type', 'notifiable_id', 'data', 'read_at', 'created_at', 'updated_at'], array_keys($dependent->getAttributes()));
});

test('a notification without the trait is unchanged', function () {
    $user = User::create();

    $user->notify(new PlainNotification);

    $this->assertSame(1, $user->notifications()->count());
    $this->assertSame(0, $this->dependencyCount());
});

test('a notification without dependencies creates no links', function () {
    $user = User::create();

    $user->notify(new DependentNotification);

    $this->assertSame(1, $user->notifications()->count());
    $this->assertSame(0, $this->dependencyCount());
});

test('mail and database delivery links only the database row', function () {
    $user = User::create();
    $order = Order::create();
    $channels = [];

    Event::listen(NotificationSent::class, function (NotificationSent $event) use (&$channels) {
        $channels[] = $event->channel;
    });

    $user->notify((new DependentNotification([], ['mail', 'database']))->dependsOn($order));

    $this->assertSame(['mail', 'database'], $channels);
    $this->assertSame(1, $user->notifications()->count());
    $this->assertSame(1, $this->dependencyCount());

    $order->delete();

    $this->assertSame(0, $this->notificationCount());
});

test('a mail only notification creates no links', function () {
    User::create()->notify((new DependentNotification([], ['mail']))->dependsOn(Order::create()));

    $this->assertSame(0, $this->notificationCount());
    $this->assertSame(0, $this->dependencyCount());
});

test('a non database event is ignored even with a model response', function () {
    $user = User::create();
    $user->notify(new PlainNotification);
    $stored = $user->notifications()->firstOrFail();

    $this->app->make(RegisterNotificationDependencies::class)->handle(
        new NotificationSent($user, (new DependentNotification)->dependsOn(Order::create()), 'mail', $stored)
    );

    $this->assertSame(0, $this->dependencyCount());
});

test('the database channel class name counts as the database channel', function () {
    User::create()->notify((new DependentNotification([], [DatabaseChannel::class]))->dependsOn(Order::create()));

    $this->assertSame(1, $this->notificationCount());
    $this->assertSame(1, $this->dependencyCount());
});

test('each recipient row gets its own links', function () {
    $alice = User::create();
    $bob = User::create();
    $order = Order::create();
    $comment = Comment::create();

    Notification::send([$alice, $bob], (new DependentNotification)->dependsOn($order, $comment));

    $aliceRow = $alice->notifications()->firstOrFail();
    $bobRow = $bob->notifications()->firstOrFail();

    $this->assertNotSame($aliceRow->getKey(), $bobRow->getKey());
    $this->assertSame(2, DB::table($this->dependencyTable())->where('notification_id', $aliceRow->getKey())->count());
    $this->assertSame(2, DB::table($this->dependencyTable())->where('notification_id', $bobRow->getKey())->count());

    $comment->delete();

    $this->assertSame(0, $this->notificationCount());
    $this->assertSame(0, $this->dependencyCount());
});

test('handling the same event twice creates no duplicates', function () {
    $user = User::create();
    $notification = (new DependentNotification)->dependsOn(Order::create(), Comment::create());
    $events = [];

    Event::listen(NotificationSent::class, function (NotificationSent $event) use (&$events) {
        $events[] = $event;
    });

    $user->notify($notification);
    $listener = $this->app->make(RegisterNotificationDependencies::class);
    $listener->handle($events[0]);
    $listener->handle($events[0]);

    $this->assertSame(1, $this->notificationCount());
    $this->assertSame(2, $this->dependencyCount());
});

test('stored notifications are read through the relation as usual', function () {
    $user = User::create();

    $user->notify((new DependentNotification)->dependsOn(Order::create()));

    $this->assertCount(1, $user->notifications);
    $this->assertCount(1, $user->unreadNotifications);

    $user->unreadNotifications->markAsRead();

    $this->assertCount(0, $user->fresh()->unreadNotifications);
    $this->assertSame(1, $this->dependencyCount());
});

test('an invalid declared dependency removes the stored notification', function () {
    $user = User::create();

    try {
        $user->notify(new DependentNotification(['not a model']));
        $this->fail('A non-model dependency was accepted.');
    } catch (InvalidArgumentException $e) {
        $this->assertStringContainsString('must only return Eloquent models, got [string]', $e->getMessage());
    }

    $this->assertSame(0, $this->notificationCount());
    $this->assertSame(0, $this->dependencyCount());
});

test('an unsaved declared dependency removes the stored notification', function () {
    $user = User::create();

    try {
        $user->notify(new DependentNotification([new Order]));
        $this->fail('An unsaved dependency was accepted.');
    } catch (InvalidArgumentException $e) {
        $this->assertStringContainsString('unsaved', $e->getMessage());
    }

    $this->assertSame(0, $this->notificationCount());
});

test('a response that is not a stored model fails loudly', function () {
    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('did not return the stored notification');

    $this->app->make(RegisterNotificationDependencies::class)->handle(
        new NotificationSent(User::create(), (new DependentNotification)->dependsOn(Order::create()), 'database', null)
    );
});
