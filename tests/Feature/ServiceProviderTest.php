<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Feature;

use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\ServiceProvider;
use Pharaonic\Laravel\NotificationDependencies\NotificationDependenciesServiceProvider;
use Pharaonic\Laravel\NotificationDependencies\Tests\TestCase;

class ServiceProviderTest extends TestCase
{
    public function test_the_provider_is_registered(): void
    {
        $this->assertNotEmpty($this->app->getProviders(NotificationDependenciesServiceProvider::class));
    }

    public function test_the_configuration_is_merged(): void
    {
        $this->assertSame([
            'table' => 'notification_dependencies',
            'all_models' => false,
            'delete_on_soft_delete' => true,
        ], config('pharaonic.notification-dependencies'));
    }

    public function test_the_listeners_are_registered(): void
    {
        $events = $this->app['events'];

        $this->assertTrue($events->hasListeners(NotificationSent::class));
        $this->assertTrue($events->hasWildcardListeners('eloquent.deleted: App\\Anything'), 'It checks all_models on each deletion.');
    }

    public function test_the_config_publishes_under_its_tags(): void
    {
        $target = $this->app->configPath('pharaonic/notification-dependencies.php');

        foreach (['pharaonic', 'laravel-notification-dependencies', 'pharaonic-config', 'notification-dependencies-config'] as $tag) {
            $paths = ServiceProvider::pathsToPublish(NotificationDependenciesServiceProvider::class, $tag);

            $this->assertContains($target, $paths, $tag);
        }
    }

    public function test_the_migrations_publish_under_their_tags(): void
    {
        $target = $this->app->databasePath('migrations');

        foreach (['pharaonic', 'laravel-notification-dependencies', 'pharaonic-migrations', 'notification-dependencies-migrations'] as $tag) {
            $paths = ServiceProvider::pathsToPublish(NotificationDependenciesServiceProvider::class, $tag);

            $this->assertContains($target, $paths, $tag);
        }
    }
}
