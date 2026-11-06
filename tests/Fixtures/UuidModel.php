<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependentNotifications;

/**
 * A UUID primary key, set on creation (HasUuids needs Laravel 9.30).
 */
class UuidModel extends Model
{
    use HasDependentNotifications;

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var array<int, string>
     */
    protected $guarded = [];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if ($model->getKey() === null) {
                $model->setAttribute($model->getKeyName(), Str::uuid()->toString());
            }
        });
    }
}
