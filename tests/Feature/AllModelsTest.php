<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Feature;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\DependentNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Order;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Tag;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\User;
use Pharaonic\Laravel\NotificationDependencies\Tests\TestCase;

/**
 * With all_models on, any model can be a dependency without the trait.
 */
class AllModelsTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $app['config']->set('pharaonic.notification-dependencies.all_models', true);
    }

    public function test_the_wildcard_listener_is_registered(): void
    {
        $this->assertTrue($this->app['events']->hasWildcardListeners('eloquent.deleted: App\\Anything'));
    }

    public function test_turning_it_off_after_boot_stops_both_sides(): void
    {
        config(['pharaonic.notification-dependencies.all_models' => false]);

        $this->expectException(\InvalidArgumentException::class);

        (new DependentNotification)->dependsOn(Tag::create());
    }

    public function test_a_model_without_the_trait_can_be_a_dependency(): void
    {
        $user = User::create();
        $tag = Tag::create();
        $order = Order::create();

        $user->notify((new DependentNotification)->dependsOn($tag, $order));

        $this->assertSame(2, $this->dependencyCount());

        $tag->delete();

        $this->assertSame(0, $this->notificationCount());
        $this->assertSame(0, $this->dependencyCount());
    }

    public function test_declared_dependencies_without_the_trait(): void
    {
        $user = User::create();
        $tag = Tag::create();

        $user->notify(new DependentNotification([$tag]));
        $tag->delete();

        $this->assertSame(0, $this->notificationCount());
    }

    public function test_a_model_with_the_trait_is_cleaned_up_once(): void
    {
        $user = User::create();
        $order = Order::create();
        $user->notify((new DependentNotification)->dependsOn($order));
        $lookups = 0;

        DB::listen(function (QueryExecuted $query) use (&$lookups) {
            $sql = strtolower($query->sql);

            if (Str::startsWith($sql, 'select') && Str::contains($sql, $this->dependencyTable())) {
                $lookups++;
            }
        });

        $order->delete();

        $this->assertSame(0, $this->notificationCount());
        $this->assertSame(1, $lookups, 'The trait cleans up; the wildcard listener skips the model.');
    }

    public function test_deleting_any_model_costs_one_lookup(): void
    {
        $user = User::create();
        $user->notify((new DependentNotification)->dependsOn(Order::create()));
        $tag = Tag::create();
        $queries = 0;

        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $tag->delete();

        $this->assertSame(2, $queries, 'The tag delete and one dependency lookup.');
        $this->assertSame(1, $this->notificationCount());
    }
}
