<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies;

use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Events\NotificationSent;
use Illuminate\Support\ServiceProvider;
use Pharaonic\Laravel\NotificationDependencies\Listeners\RegisterNotificationDependencies;
use Pharaonic\Laravel\NotificationDependencies\Support\NotificationDependenciesManager;

final class NotificationDependenciesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/notification-dependencies.php', 'pharaonic.notification-dependencies');
    }

    public function boot(Dispatcher $events): void
    {
        $events->listen(NotificationSent::class, RegisterNotificationDependencies::class);

        // Models with HasDependentNotifications clean up through their own
        // deleted event; this covers every other model when all_models is on.
        // The setting is read on each deletion, like dependsOn() reads it, so
        // a change made after boot applies to both.
        $events->listen('eloquent.deleted: *', function (string $event, array $payload) {
            $model = $payload[0] ?? null;

            if ($model instanceof Model
                && NotificationDependenciesManager::watchesAllModels()
                && ! NotificationDependenciesManager::usesTrait($model)) {
                NotificationDependenciesManager::purge($model);
            }
        });

        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/notification-dependencies.php' => $this->app->configPath('pharaonic/notification-dependencies.php'),
            ], ['pharaonic', 'laravel-notification-dependencies', 'pharaonic-config', 'notification-dependencies-config']);

            $this->publishes([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], ['pharaonic', 'laravel-notification-dependencies', 'pharaonic-migrations', 'notification-dependencies-migrations']);
        }
    }
}
