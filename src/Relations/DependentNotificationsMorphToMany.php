<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Relations;

use Illuminate\Contracts\Database\Query\Expression as ExpressionContract;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Database\Grammar;
use Illuminate\Notifications\DatabaseNotification;

/**
 * The dependentNotifications() relation of a dependable model.
 *
 * The dependency table stores ids as strings, so integer, UUID and ULID keys
 * can share it. This relation compares the parent key as a string too:
 * PostgreSQL refuses to compare a varchar column with a bigint one, and on
 * MySQL and MariaDB the string comparison is what lets the dependable_id
 * index be used.
 *
 * @internal
 *
 * @template TDeclaringModel of Model
 *
 * @extends MorphToMany<DatabaseNotification, TDeclaringModel>
 */
final class DependentNotificationsMorphToMany extends MorphToMany
{
    /**
     * Declared here so PHPStan 2.1.0, the line's lowest, infers TDeclaringModel
     * from `new DependentNotificationsMorphToMany(...)`: it does not read the
     * inherited constructor.
     *
     * @param  Builder<DatabaseNotification>  $query
     * @param  TDeclaringModel  $parent
     * @param  string  $name
     * @param  string  $table
     * @param  string  $foreignPivotKey
     * @param  string  $relatedPivotKey
     * @param  string  $parentKey
     * @param  string  $relatedKey
     * @param  string|null  $relationName
     * @param  bool  $inverse
     */
    public function __construct(Builder $query, Model $parent, $name, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName = null, $inverse = false)
    {
        parent::__construct($query, $parent, $name, $table, $foreignPivotKey, $relatedPivotKey, $parentKey, $relatedKey, $relationName, $inverse);
    }

    /**
     * Constrain a lazy load to the parent's key, as a string.
     *
     * @return $this
     */
    protected function addWhereConstraints()
    {
        $this->query->where(
            $this->getQualifiedForeignPivotKeyName(),
            '=',
            self::stringKey($this->parent->getAttribute($this->parentKey))
        );

        $this->query->where($this->table.'.'.$this->morphType, $this->morphClass);

        return $this;
    }

    /**
     * Constrain eager loading to the parents' keys, as strings.
     *
     * @param  array<int, TDeclaringModel>  $models
     * @return void
     */
    public function addEagerConstraints(array $models)
    {
        $keys = [];

        foreach ($this->getKeys($models, $this->parentKey) as $key) {
            $key = self::stringKey($key);

            if ($key !== null) {
                $keys[] = $key;
            }
        }

        $this->query->whereIn($this->getQualifiedForeignPivotKeyName(), $keys);
        $this->query->where($this->table.'.'.$this->morphType, $this->morphClass);
    }

    /**
     * The subquery behind has(), whereHas() and withCount().
     *
     * @param  Builder<DatabaseNotification>  $query
     * @param  Builder<TDeclaringModel>  $parentQuery
     * @param  mixed  $columns
     * @return Builder<DatabaseNotification>
     */
    public function getRelationExistenceQuery(Builder $query, Builder $parentQuery, $columns = ['*'])
    {
        $base = $query->getQuery();
        $connection = $base->getConnection();
        $driver = $connection instanceof Connection ? $connection->getDriverName() : null;

        switch ($driver) {
            case 'mysql':
            case 'mariadb':
                // CONCAT() takes the collation of the dependency column, so a
                // connection collation that differs from the table's cannot fail.
                $template = 'concat(%s)';

                break;
            case 'pgsql':
                $template = 'cast(%s as varchar)';

                break;
            case 'sqlite':
                $template = 'cast(%s as text)';

                break;
            default:
                $template = null;
        }

        if ($template === null || $parentQuery->getQuery()->from == $base->from) {
            return parent::getRelationExistenceQuery($query, $parentQuery, $columns);
        }

        // The grammar wraps the column when the query is compiled.
        $parentKeyAsString = new class($template, $this->getQualifiedParentKeyName()) implements ExpressionContract
        {
            public function __construct(private string $template, private string $column) {}

            public function getValue(Grammar $grammar): string
            {
                return sprintf($this->template, $grammar->wrap($this->column));
            }
        };

        $this->performJoin($query);

        $query->select($columns);
        $query->whereColumn($parentKeyAsString, '=', $this->getExistenceCompareKey());
        $query->where($this->table.'.'.$this->morphType, $this->morphClass);

        return $query;
    }

    /**
     * @param  mixed  $key
     */
    private static function stringKey($key): ?string
    {
        if (is_int($key) || is_string($key)) {
            return (string) $key;
        }

        return null;
    }
}
