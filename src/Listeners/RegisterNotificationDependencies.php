<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Listeners;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Events\NotificationSent;
use InvalidArgumentException;
use Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependencies;
use Pharaonic\Laravel\NotificationDependencies\Support\NotificationDependenciesManager;
use ReflectionProperty;
use RuntimeException;
use Throwable;

/**
 * Links each database notification row to the models its notification
 * depends on, right after the database channel stored it.
 *
 * @internal
 *
 * @phpstan-import-type Identity from NotificationDependenciesManager
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
            $identities = $this->identities($notification);
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

    /**
     * The dependencies declared by dependencies(), then those added with
     * dependsOn(), without duplicates.
     *
     * @return array<string, Identity>
     */
    private function identities(object $notification): array
    {
        $identities = [];

        $declared = method_exists($notification, 'dependencies') ? $notification->dependencies() : [];

        if (! is_array($declared)) {
            throw new InvalidArgumentException(sprintf('%s::dependencies() must return an array of models.', get_class($notification)));
        }

        foreach ($declared as $model) {
            if (! $model instanceof Model) {
                throw new InvalidArgumentException(sprintf(
                    '%s::dependencies() must only return Eloquent models, got [%s].',
                    get_class($notification),
                    get_debug_type($model)
                ));
            }

            $identity = NotificationDependenciesManager::identify($model);
            $identities[$identity['signature']] = $identity;
        }

        $property = new ReflectionProperty($notification, 'notificationDependencies');
        $property->setAccessible(true);
        $added = $property->getValue($notification);

        foreach (is_array($added) ? $added : [] as $identity) {
            if (self::isIdentity($identity) && ! isset($identities[$identity['signature']])) {
                $identities[$identity['signature']] = $identity;
            }
        }

        return $identities;
    }

    /**
     * @param  mixed  $value
     *
     * @phpstan-assert-if-true Identity $value
     */
    private static function isIdentity($value): bool
    {
        if (! is_array($value)) {
            return false;
        }

        foreach (['type', 'key', 'class', 'connection', 'signature'] as $field) {
            if (! isset($value[$field]) || ! is_string($value[$field])) {
                return false;
            }
        }

        return true;
    }
}
