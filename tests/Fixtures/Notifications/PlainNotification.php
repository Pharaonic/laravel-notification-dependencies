<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications;

use Illuminate\Notifications\Notification;

/**
 * An ordinary database notification, without the trait.
 */
class PlainNotification extends Notification
{
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
        return ['message' => 'Plain.'];
    }
}
