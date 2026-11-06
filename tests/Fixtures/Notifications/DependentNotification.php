<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependencies;

/**
 * A notification whose channels and declared dependencies the test chooses.
 */
class DependentNotification extends Notification
{
    use HasDependencies;

    /** @var array<int, string> */
    public $channels;

    /** @var array<int, mixed> */
    public $declared;

    /**
     * @param  array<int, mixed>  $declared
     * @param  array<int, string>  $channels
     */
    public function __construct(array $declared = [], array $channels = ['database'])
    {
        $this->declared = $declared;
        $this->channels = $channels;
    }

    /**
     * @return array<int, Model>
     */
    public function dependencies(): array
    {
        /** @var array<int, Model> */
        return $this->declared;
    }

    /**
     * @param  mixed  $notifiable
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return $this->channels;
    }

    /**
     * @param  mixed  $notifiable
     * @return array<string, string>
     */
    public function toDatabase($notifiable): array
    {
        return ['message' => 'Your order received a comment.'];
    }

    /**
     * @param  mixed  $notifiable
     */
    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)->line('Your order received a comment.');
    }
}
