<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Comment;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\QueuedNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\SerializingQueuedNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Order;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\User;
use Pharaonic\Laravel\NotificationDependencies\Tests\TestCase;

/**
 * Notifications go through the database queue and a real queue:work run,
 * so every one is serialized, stored, and unserialized before delivery.
 */
class QueueTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('queue.default', 'database');
    }

    public function test_dependency_identities_survive_the_queue_and_are_linked_on_delivery(): void
    {
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

        $this->work();

        $this->assertSame(1, $this->notificationCount());
        $this->assertSame(2, $this->dependencyCount());

        $comment->delete();

        $this->assertSame(0, $this->notificationCount());
    }

    public function test_a_dependency_deleted_while_queued_leaves_no_notification(): void
    {
        $user = User::create();
        $order = Order::create();
        $comment = Comment::create();

        $user->notify(new QueuedNotification($order, $comment));
        $comment->delete();

        $this->work();

        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(0, $this->notificationCount());
        $this->assertSame(0, $this->dependencyCount());
    }

    public function test_a_serializes_models_notification_links_its_declared_dependencies(): void
    {
        $user = User::create();
        $order = Order::create();

        $user->notify(new SerializingQueuedNotification($order));
        $this->work();

        $this->assertSame(1, $this->notificationCount());
        $this->assertSame(1, $this->dependencyCount());
    }

    public function test_a_serializes_models_notification_whose_model_is_gone_is_never_stored(): void
    {
        $user = User::create();
        $order = Order::create();

        $user->notify(new SerializingQueuedNotification($order));
        $order->delete();

        $this->work();

        $this->assertSame(1, DB::table('failed_jobs')->count(), 'SerializesModels fails the job when its model is missing.');
        $this->assertSame(0, $this->notificationCount());
        $this->assertSame(0, $this->dependencyCount());
    }

    public function test_each_queued_recipient_gets_its_own_links(): void
    {
        $alice = User::create();
        $bob = User::create();
        $order = Order::create();

        \Illuminate\Support\Facades\Notification::send([$alice, $bob], new QueuedNotification($order));

        $this->work();
        $this->work();

        $this->assertSame(2, $this->notificationCount());
        $this->assertSame(2, $this->dependencyCount());

        $order->delete();

        $this->assertSame(0, $this->notificationCount());
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
