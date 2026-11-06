<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Queue\SerializesModels;
use Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependencies;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Order;

/**
 * A queued notification that keeps its model with SerializesModels and
 * declares it in dependencies().
 */
class SerializingQueuedNotification extends Notification implements ShouldQueue
{
    use HasDependencies;
    use Queueable;
    use SerializesModels;

    /** @var Order */
    public $order;

    public function __construct(Order $order)
    {
        $this->order = $order;
    }

    /**
     * @return array<int, \Illuminate\Database\Eloquent\Model>
     */
    public function dependencies(): array
    {
        return [$this->order];
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
     * @return array<string, mixed>
     */
    public function toDatabase($notifiable): array
    {
        return ['order' => $this->order->getKey()];
    }
}
