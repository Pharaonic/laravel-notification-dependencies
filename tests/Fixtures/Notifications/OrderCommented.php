<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications;

use Illuminate\Notifications\Notification;
use Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependencies;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Comment;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Order;

/**
 * Dependencies declared inside the class, as in the README.
 */
class OrderCommented extends Notification
{
    use HasDependencies;

    /** @var Order */
    protected $order;

    /** @var Comment */
    protected $comment;

    public function __construct(Order $order, Comment $comment)
    {
        $this->order = $order;
        $this->comment = $comment;
    }

    /**
     * @return array<int, \Illuminate\Database\Eloquent\Model>
     */
    public function dependencies(): array
    {
        return [$this->order, $this->comment];
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
        return ['message' => 'Your order received a comment.'];
    }
}
