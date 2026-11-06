<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Actions;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\NotificationDependencies\Support\NotificationDependenciesManager as Manager;

/**
 * Deletes every database notification that depends on a deleted model:
 * one indexed lookup, then set-based deletes in batches, without loading
 * any notification.
 *
 * @internal
 */
final class PurgeDependentNotifications
{
    public function __invoke(Model $model): void
    {
        if (Manager::isSoftDelete($model) && ! Manager::deletesOnSoftDelete()) {
            return;
        }

        $identity = Manager::identityOf($model);

        if ($identity === null) {
            return;
        }

        $notification = Manager::notificationModel();

        do {
            $found = Manager::constrain(Manager::table($notification), $identity)
                ->limit(Manager::BATCH_SIZE)
                ->pluck('notification_id');

            $ids = [];

            foreach ($found as $id) {
                if (is_string($id) || is_int($id)) {
                    $ids[] = (string) $id;
                }
            }

            if ($ids === []) {
                return;
            }

            $notification->getConnection()->transaction(function () use ($notification, $ids) {
                Manager::discard($notification, $ids);
            });
        } while (count($found) === Manager::BATCH_SIZE);
    }
}
