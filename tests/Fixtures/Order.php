<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependentNotifications;

class Order extends Model
{
    use HasDependentNotifications;

    protected $table = 'orders';

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
