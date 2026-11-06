<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notifiable;

class User extends Model
{
    use Notifiable;

    protected $table = 'users';

    /**
     * @var array<int, string>
     */
    protected $guarded = [];

    public function routeNotificationForMail(): string
    {
        return 'user@example.com';
    }
}
