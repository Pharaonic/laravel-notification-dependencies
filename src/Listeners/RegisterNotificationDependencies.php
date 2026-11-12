<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Events\NotificationSent;
use Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependencies;
use Pharaonic\Laravel\NotificationDependencies\Support\NotificationDependenciesManager;
use RuntimeException;
use Throwable;

/**
 * Links each database notification row to the models its notification
 * depends on, right after the database channel stored it.
 *
 * @internal
 */
final class RegisterNotificationDependencies
{
    public function handle(NotificationSent $event): void
    {
        if (! $this->isDatabaseChannel($event->channel)) {
            return;
        }

        $notification = $event->notification;

        if (! NotificationDependenciesManager::hasTrait($notification, HasDependencies::class)) {
            return;
        }

        $stored = $event->response;

        try {
            $identities = NotificationDependenciesManager::identitiesOf($notification);
        } catch (Throwable $e) {
            if ($this->isStoredNotification($stored)) {
                NotificationDependenciesManager::discardNotification($stored);
            }

            throw $e;
        }

        if ($identities === []) {
            return;
        }

        if (! $this->isStoredNotification($stored)) {
            throw new RuntimeException(sprintf(
                'The database channel did not return the stored notification for [%s], so its dependencies cannot be linked to it.',
                get_class($notification)
            ));
        }

        NotificationDependenciesManager::link($stored, $identities);
    }

    /**
     * @param  mixed  $channel
     */
    private function isDatabaseChannel($channel): bool
    {
        return $channel === 'database'
            || (is_string($channel) && is_a($channel, DatabaseChannel::class, true));
    }

    /**
     * @param  mixed  $stored
     *
     * @phpstan-assert-if-true Model $stored
     */
    private function isStoredNotification($stored): bool
    {
        if (! $stored instanceof Model || ! $stored->exists) {
            return false;
        }

        $key = $stored->getKey();

        return (is_string($key) && $key !== '') || is_int($key);
    }
}
