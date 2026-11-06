<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependencies;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Order;

/**
 * Dependencies passed through the constructor, as in the README.
 */
class OrderUpdated extends Notification
{
    use HasDependencies;

    /**
     * @param  array<int, Model>  $dependencies
     */
    public function __construct(Order $order, array $dependencies = [])
    {
        $this->dependsOn($order);
        $this->dependsOn(...$dependencies);
    }

    /**
     * @param  mixed  $notifiable
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return ['database'];
    }

    /**
     * @param  mixed  $notifiable
     * @return array<string, string>
     */
    public function toDatabase($notifiable): array
    {
        return ['message' => 'Your order was updated.'];
    }
}
