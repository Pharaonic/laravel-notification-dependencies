<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Actions;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\NotificationDependencies\Support\NotificationDependenciesManager as Manager;

/**
 * Whether every dependency of a notification still exists. A soft-deleted
 * one only counts as existing when soft deletes do not invalidate
 * notifications.
 *
 * @internal
 *
 * @phpstan-import-type Identity from Manager
 */
final class CheckDependencies
{
    /**
     * @param  array<string, Identity>  $identities
     * @param  bool  $lock  read the rows for update, inside the caller's transaction
     */
    public function __invoke(array $identities, bool $lock): bool
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
                if ($this->countExisting($model, $keys, $lock) === count($keys)) {
                    continue;
                }

                // Fewer rows than keys: a key is missing, or the column's
                // collation matched two keys to one row. Ask the database
                // about each key, so it decides what equal means.
                foreach ($keys as $key) {
                    if ($this->countExisting($model, [$key], $lock) === 0) {
                        return false;
                    }
                }
            }
        }

        return true;
    }

    /**
     * How many of the dependency rows exist. A plain table query: no global
     * scope (a tenant or visibility scope) can make an existing dependency
     * look deleted.
     *
     * @param  array<int, string>  $keys
     */
    private function countExisting(Model $model, array $keys, bool $lock): int
    {
        $query = $model->getConnection()->table($model->getTable())
            ->whereIn($model->getQualifiedKeyName(), $keys);

        if ($lock) {
            $query->lockForUpdate();
        }

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
