<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependentNotifications;

class Product extends Model
{
    use HasDependentNotifications;

    protected $table = 'products';

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
