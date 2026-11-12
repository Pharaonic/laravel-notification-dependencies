<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
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
use Pharaonic\Laravel\NotificationDependencies\Tests\TestCase;

/**
 * A notification with a deleted dependency is not sent on any channel.
 */
class CancellationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        RecordingChannel::$sent = [];
    }

    public function test_every_channel_is_sent_while_the_dependencies_exist(): void
    {
        $user = User::create();

        $user->notify(new MultiChannelNotification(Order::create(), Comment::create()));

        $this->assertSame([MultiChannelNotification::class], RecordingChannel::$sent);
        $this->assertSame(1, $this->notificationCount());
        $this->assertSame(2, $this->dependencyCount());
    }

    public function test_a_dependency_deleted_while_queued_cancels_every_channel(): void
    {
        config(['queue.default' => 'database']);

        $user = User::create();
        $order = Order::create();
        $comment = Comment::create();

        $user->notify(new MultiChannelNotification($order, $comment));
        $this->assertSame(2, DB::table('jobs')->count(), 'One job per channel.');

        $comment->delete();
        $this->work();
        $this->work();

        $this->assertSame([], RecordingChannel::$sent);
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(0, $this->notificationCount());
        $this->assertSame(0, $this->dependencyCount());
    }

    public function test_a_dependency_row_deleted_without_events_cancels_every_channel(): void
    {
        $user = User::create();
        $order = Order::create();

        DB::table('orders')->where('id', $order->getKey())->delete();
        $user->notify(new MultiChannelNotification($order));

        $this->assertSame([], RecordingChannel::$sent);
        $this->assertSame(0, $this->notificationCount());
        $this->assertSame(0, $this->dependencyCount());
    }

    public function test_a_soft_deleted_dependency_cancels_every_channel_by_default(): void
    {
        $user = User::create();
        $order = SoftOrder::create();
        $notification = new MultiChannelNotification($order);

        $order->delete();
        $user->notify($notification);

        $this->assertSame([], RecordingChannel::$sent);
        $this->assertSame(0, $this->notificationCount());
    }

    public function test_a_soft_deleted_dependency_is_sent_when_soft_deletes_are_kept(): void
    {
        config(['pharaonic.notification-dependencies.delete_on_soft_delete' => false]);

        $user = User::create();
        $order = SoftOrder::create();
        $notification = new MultiChannelNotification($order);

        $order->delete();
        $user->notify($notification);

        $this->assertSame([MultiChannelNotification::class], RecordingChannel::$sent);
        $this->assertSame(1, $this->notificationCount());
    }

    public function test_other_sending_listeners_still_run_when_the_dependencies_exist(): void
    {
        $seen = [];

        Event::listen(NotificationSending::class, function (NotificationSending $event) use (&$seen) {
            $seen[] = $event->channel;
        });

        User::create()->notify(new MultiChannelNotification(Order::create()));

        $this->assertSame([RecordingChannel::class, 'database'], $seen);
        $this->assertSame([MultiChannelNotification::class], RecordingChannel::$sent);
    }

    public function test_a_notification_without_the_trait_is_never_checked(): void
    {
        DB::enableQueryLog();

        User::create()->notify(new PlainNotification);

        $queries = array_column(DB::getQueryLog(), 'query');

        $this->assertSame(1, $this->notificationCount());
        $this->assertCount(2, $queries, 'The user and the notification inserts only.');
    }

    private function work(): void
    {
        $this->app->make(Kernel::class)->call('queue:work', [
            'connection' => 'database',
            '--once' => true,
            '--tries' => 1,
            '--sleep' => 0,
        ]);
    }
}
