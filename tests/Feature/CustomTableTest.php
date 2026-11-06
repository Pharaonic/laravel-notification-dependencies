<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Pharaonic\Laravel\NotificationDependencies\Models\NotificationDependency;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Comment;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\DependentNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Order;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\User;
use Pharaonic\Laravel\NotificationDependencies\Tests\TestCase;

class CustomTableTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('pharaonic.notification-dependencies.table', 'custom_dependencies');
    }

    public function test_the_migration_and_every_query_use_the_configured_table(): void
    {
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
    }

    public function test_an_empty_setting_falls_back_to_the_default_table(): void
    {
        config(['pharaonic.notification-dependencies.table' => '']);

        $this->assertSame(NotificationDependency::DEFAULT_TABLE, (new NotificationDependency)->getTable());
    }
}
