<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Listeners;

use Illuminate\Notifications\Events\NotificationSending;
use Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependencies;
use Pharaonic\Laravel\NotificationDependencies\Support\NotificationDependenciesManager;

/**
 * Stops a notification on every channel when one of its dependencies is
 * gone by the time it is sent: deleted while it waited in the queue, or
 * deleted without a model event.
 *
 * Laravel fires NotificationSending once per notifiable and channel, and a
 * queued notification runs as one job per channel, so each send is checked
 * when it happens.
 *
 * @internal
 */
final class CancelNotificationsWithDeletedDependencies
{
    /**
     * False cancels the send. Null lets the other listeners decide, since
     * Laravel stops at the first listener that returns anything.
     */
    public function handle(NotificationSending $event): ?bool
    {
        $notification = $event->notification;

        if (! NotificationDependenciesManager::hasTrait($notification, HasDependencies::class)) {
            return null;
        }

        $identities = NotificationDependenciesManager::identitiesOf($notification);

        if ($identities === [] || NotificationDependenciesManager::dependenciesExist($identities)) {
            return null;
        }

        return false;
    }
}
