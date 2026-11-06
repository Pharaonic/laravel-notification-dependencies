<?php

use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\ServiceProvider;
use Pharaonic\Laravel\NotificationDependencies\NotificationDependenciesServiceProvider;

test('the provider is registered', function () {
    $this->assertNotEmpty($this->app->getProviders(NotificationDependenciesServiceProvider::class));
});

test('the configuration is merged', function () {
    $this->assertSame([
        'table' => 'notification_dependencies',
        'all_models' => false,
        'delete_on_soft_delete' => true,
    ], config('pharaonic.notification-dependencies'));
});

test('the listeners are registered', function () {
    $events = $this->app['events'];

    $this->assertTrue($events->hasListeners(NotificationSent::class));
    $this->assertTrue($events->hasWildcardListeners('eloquent.deleted: App\\Anything'), 'It checks all_models on each deletion.');
});

test('the config publishes under its tags', function () {
    $target = $this->app->configPath('pharaonic/notification-dependencies.php');

    foreach (['pharaonic', 'laravel-notification-dependencies', 'pharaonic-config', 'notification-dependencies-config'] as $tag) {
        $paths = ServiceProvider::pathsToPublish(NotificationDependenciesServiceProvider::class, $tag);

        $this->assertContains($target, $paths, $tag);
    }
});

test('the migrations publish under their tags', function () {
    $target = $this->app->databasePath('migrations');

    foreach (['pharaonic', 'laravel-notification-dependencies', 'pharaonic-migrations', 'notification-dependencies-migrations'] as $tag) {
        $paths = ServiceProvider::pathsToPublish(NotificationDependenciesServiceProvider::class, $tag);

        $this->assertContains($target, $paths, $tag);
    }
});
