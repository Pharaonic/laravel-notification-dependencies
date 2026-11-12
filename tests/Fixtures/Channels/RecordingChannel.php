<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Channels;

use Illuminate\Notifications\Notification;

/**
 * A channel that only records what it was asked to send, standing in for
 * mail, broadcast, or any other channel that is not the database.
 */
class RecordingChannel
{
    /**
     * @var array<int, class-string>
     */
    public static $sent = [];

    /**
     * @param  mixed  $notifiable
     */
    public function send($notifiable, Notification $notification): void
    {
        self::$sent[] = get_class($notification);
    }
}
