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

                if (! $this->allExist($identities)) {
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

    /**
     * Whether every dependency still exists. A soft-deleted one only counts
     * as existing when soft deletes do not invalidate notifications.
     *
     * @param  array<string, Identity>  $identities
     */
    private function allExist(array $identities): bool
    {
        $groups = [];

        foreach ($identities as $identity) {
            $groups[$identity['class']."\0".$identity['connection']][] = $identity;
        }

        foreach ($groups as $group) {
            // A queued identity may name a class renamed or removed since:
            // that dependency no longer exists.
            $class = $group[0]['class'];

            if (! is_subclass_of($class, Model::class)) {
                return false;
            }

            $model = new $class;
            $model->setConnection($group[0]['connection']);

            foreach (array_chunk(array_column($group, 'key'), Manager::BATCH_SIZE) as $keys) {
                if ($this->countExisting($model, $keys) === count($keys)) {
                    continue;
                }

                // Fewer rows than keys: a key is missing, or the column's
                // collation matched two keys to one row. Ask the database
                // about each key, so it decides what equal means.
                foreach ($keys as $key) {
                    if ($this->countExisting($model, [$key]) === 0) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * How many of the dependency rows exist, read with a lock for
     * update, so a concurrent deletion waits for the links to be committed.
     * A plain table query: no global scope (a tenant or visibility scope)
     * can make an existing dependency look deleted.
     *
     * @param  array<int, string>  $keys
     */
    private function countExisting(Model $model, array $keys): int
    {
        $query = $model->getConnection()->table($model->getTable())
            ->whereIn($model->getQualifiedKeyName(), $keys)
            ->lockForUpdate();

        if (Manager::deletesOnSoftDelete() && method_exists($model, 'getQualifiedDeletedAtColumn')) {
            $column = $model->getQualifiedDeletedAtColumn();

            if (is_string($column)) {
                $query->whereNull($column);
            }
        }

        // Selected rather than counted: PostgreSQL does not lock aggregates.
        return $query->pluck($model->getQualifiedKeyName())->count();
    }
}
