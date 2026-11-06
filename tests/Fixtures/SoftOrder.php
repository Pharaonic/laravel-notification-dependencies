<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependentNotifications;

class SoftOrder extends Model
{
    use HasDependentNotifications;
    use SoftDeletes;

    protected $table = 'soft_orders';

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
