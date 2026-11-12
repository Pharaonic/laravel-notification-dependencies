<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependencies;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Channels\RecordingChannel;

/**
 * A queued notification sent on another channel before the database one.
 */
class MultiChannelNotification extends Notification implements ShouldQueue
{
    use HasDependencies;
    use Queueable;

    public function __construct(Model ...$dependencies)
    {
        $this->dependsOn(...$dependencies);
    }

    /**
     * @param  mixed  $notifiable
     * @return array<int, string>
     */
    public function via($notifiable): array
    {
        return [RecordingChannel::class, 'database'];
    }

    /**
     * @param  mixed  $notifiable
     * @return array<string, string>
     */
    public function toDatabase($notifiable): array
    {
        return ['message' => 'Sent everywhere.'];
    }
}
