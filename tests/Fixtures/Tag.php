<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A model without HasDependentNotifications.
 */
class Tag extends Model
{
    protected $table = 'tags';

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
