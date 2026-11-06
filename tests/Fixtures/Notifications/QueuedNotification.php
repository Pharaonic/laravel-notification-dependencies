<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Notifications\Notification;
use Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependencies;

/**
 * A queued notification that keeps no model: only the dependency identities
 * travel through the queue.
 */
class QueuedNotification extends Notification implements ShouldQueue
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
        return ['database'];
    }

    /**
     * @param  mixed  $notifiable
     * @return array<string, string>
     */
    public function toDatabase($notifiable): array
    {
        return ['message' => 'Queued.'];
    }
}
