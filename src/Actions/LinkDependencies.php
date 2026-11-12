<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Actions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\DatabaseNotification;
use InvalidArgumentException;
use Pharaonic\Laravel\NotificationDependencies\Support\NotificationDependenciesManager as Manager;
use Throwable;

/**
 * Links a stored database notification to its dependencies.
 *
 * The links are inserted first, then the dependency rows are read with a
 * lock. A concurrent deletion either commits before that read, so the check
 * sees the dependency gone, or waits for this transaction to commit, so its
 * cleanup sees the links. When a dependency is already gone, the
 * notification is deleted. When anything fails, the notification and its
 * links are deleted and the exception is rethrown.
 *
 * @internal
 *
 * @phpstan-import-type Identity from Manager
 */
final class LinkDependencies
{
    /**
     * @param  array<string, Identity>  $identities
     */
    public function __invoke(Model $notification, array $identities): void
    {
        if (! Manager::isOnNotificationConnection($notification)) {
            // Nothing was linked yet, and that connection may not even have
            // the dependency table: only the notification row is removed.
            $notification->getConnection()->table($notification->getTable())
                ->where($notification->getKeyName(), Manager::idOf($notification))
                ->delete();

            throw new InvalidArgumentException(sprintf(
                'The notification was stored on the [%s] connection, but dependencies are only purged on [%s], '
                .'the connection of %s. A notifiable with its own connection is not supported.',
                $notification->getConnection()->getName(),
                Manager::notificationModel()->getConnection()->getName(),
                DatabaseNotification::class
            ));
        }

        try {
            $notification->getConnection()->transaction(function () use ($notification, $identities) {
                $id = Manager::idOf($notification);

                $this->insert($notification, $id, $identities);

                if (! Manager::dependenciesExist($identities, true)) {
                    Manager::discard($notification, [$id]);
                }
            });
        } catch (Throwable $e) {
            Manager::discardNotification($notification);

            throw $e;
        }
    }

    /**
     * Insert the links; a link that already exists is left alone.
     *
     * @param  array<string, Identity>  $identities
     */
    private function insert(Model $notification, string $id, array $identities): void
    {
        $now = $notification->freshTimestampString();
        $rows = [];

        foreach ($identities as $identity) {
            $rows[] = [
                'notification_id' => $id,
                'dependable_type' => $identity['type'],
                'dependable_id' => $identity['key'],
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, Manager::BATCH_SIZE) as $chunk) {
            Manager::table($notification)->insertOrIgnore($chunk);
        }
    }
}
