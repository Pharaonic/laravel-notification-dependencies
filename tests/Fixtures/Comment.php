<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependentNotifications;

class Comment extends Model
{
    use HasDependentNotifications;

    protected $table = 'comments';

    /**
     * @var array<int, string>
     */
    protected $guarded = [];
}
