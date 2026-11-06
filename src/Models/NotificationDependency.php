<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Config;
use Pharaonic\Laravel\NotificationDependencies\Support\NotificationDependenciesManager;

/**
 * Links one stored database notification to one model it depends on.
 *
 * @property int $id
 * @property string $notification_id
 * @property string $dependable_type
 * @property string $dependable_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 */
class NotificationDependency extends Model
{
    public const DEFAULT_TABLE = 'notification_dependencies';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'notification_id',
        'dependable_type',
        'dependable_id',
    ];

    /**
     * The table from the pharaonic.notification-dependencies.table setting,
     * unless a subclass sets $table.
     *
     * @return string
     */
    public function getTable()
    {
        if ($this->table) {
            return $this->table;
        }

        $table = Config::get('pharaonic.notification-dependencies.table', self::DEFAULT_TABLE);

        return is_string($table) && $table !== '' ? $table : self::DEFAULT_TABLE;
    }

    /**
     * The stored database notification.
     */
    public function notification(): BelongsTo
    {
        return $this->belongsTo(DatabaseNotification::class, 'notification_id');
    }

    /**
     * The model the notification depends on.
     */
    public function dependable(): MorphTo
    {
        return $this->morphTo('dependable', 'dependable_type', 'dependable_id');
    }

    /**
     * Only the links to this model.
     */
    public function scopeWhereDependable(Builder $query, Model $model): void
    {
        $identity = NotificationDependenciesManager::identityOf($model);

        if ($identity === null) {
            $query->whereRaw('1 = 0');

            return;
        }

        NotificationDependenciesManager::constrain($query->getQuery(), $identity);
    }
}
