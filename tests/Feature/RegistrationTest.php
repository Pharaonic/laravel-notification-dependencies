<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Feature;

use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use InvalidArgumentException;
use Pharaonic\Laravel\NotificationDependencies\Listeners\RegisterNotificationDependencies;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Comment;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\DependentNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\PlainNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Order;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Product;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\User;
use Pharaonic\Laravel\NotificationDependencies\Tests\TestCase;
use RuntimeException;

class RegistrationTest extends TestCase
{
    public function test_one_dependency_creates_one_link_to_the_stored_row(): void
    {
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
    }

    public function test_three_dependencies_create_three_links(): void
    {
        User::create()->notify((new DependentNotification)->dependsOn(Order::create(), Comment::create(), Product::create()));

        $this->assertSame(3, $this->dependencyCount());
    }

    public function test_the_stored_notification_is_left_as_laravel_wrote_it(): void
    {
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
    }

    public function test_a_notification_without_the_trait_is_unchanged(): void
    {
        $user = User::create();

        $user->notify(new PlainNotification);

        $this->assertSame(1, $user->notifications()->count());
        $this->assertSame(0, $this->dependencyCount());
    }

    public function test_a_notification_without_dependencies_creates_no_links(): void
    {
        $user = User::create();

        $user->notify(new DependentNotification);

        $this->assertSame(1, $user->notifications()->count());
        $this->assertSame(0, $this->dependencyCount());
    }

    public function test_mail_and_database_delivery_links_only_the_database_row(): void
    {
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
    }

    public function test_a_mail_only_notification_creates_no_links(): void
    {
        User::create()->notify((new DependentNotification([], ['mail']))->dependsOn(Order::create()));

        $this->assertSame(0, $this->notificationCount());
        $this->assertSame(0, $this->dependencyCount());
    }

    public function test_a_non_database_event_is_ignored_even_with_a_model_response(): void
    {
        $user = User::create();
        $user->notify(new PlainNotification);
        $stored = $user->notifications()->firstOrFail();

        $this->app->make(RegisterNotificationDependencies::class)->handle(
            new NotificationSent($user, (new DependentNotification)->dependsOn(Order::create()), 'mail', $stored)
        );

        $this->assertSame(0, $this->dependencyCount());
    }

    public function test_the_database_channel_class_name_counts_as_the_database_channel(): void
    {
        User::create()->notify((new DependentNotification([], [DatabaseChannel::class]))->dependsOn(Order::create()));

        $this->assertSame(1, $this->notificationCount());
        $this->assertSame(1, $this->dependencyCount());
    }

    public function test_each_recipient_row_gets_its_own_links(): void
    {
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
    }

    public function test_handling_the_same_event_twice_creates_no_duplicates(): void
    {
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
    }

    public function test_stored_notifications_are_read_through_the_relation_as_usual(): void
    {
        $user = User::create();

        $user->notify((new DependentNotification)->dependsOn(Order::create()));

        $this->assertCount(1, $user->notifications);
        $this->assertCount(1, $user->unreadNotifications);

        $user->unreadNotifications->markAsRead();

        $this->assertCount(0, $user->fresh()->unreadNotifications);
        $this->assertSame(1, $this->dependencyCount());
    }

    public function test_an_invalid_declared_dependency_removes_the_stored_notification(): void
    {
        $user = User::create();

        try {
            $user->notify(new DependentNotification(['not a model']));
            $this->fail('A non-model dependency was accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('must only return Eloquent models, got [string]', $e->getMessage());
        }

        $this->assertSame(0, $this->notificationCount());
        $this->assertSame(0, $this->dependencyCount());
    }

    public function test_an_unsaved_declared_dependency_removes_the_stored_notification(): void
    {
        $user = User::create();

        try {
            $user->notify(new DependentNotification([new Order]));
            $this->fail('An unsaved dependency was accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('unsaved', $e->getMessage());
        }

        $this->assertSame(0, $this->notificationCount());
    }

    public function test_a_response_that_is_not_a_stored_model_fails_loudly(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('did not return the stored notification');

        $this->app->make(RegisterNotificationDependencies::class)->handle(
            new NotificationSent(User::create(), (new DependentNotification)->dependsOn(Order::create()), 'database', null)
        );
    }
}
