<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Concerns;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Illuminate\Notifications\DatabaseNotification;
use Pharaonic\Laravel\NotificationDependencies\Models\NotificationDependency;
use Pharaonic\Laravel\NotificationDependencies\Relations\DependentNotificationsMorphToMany;
use Pharaonic\Laravel\NotificationDependencies\Support\NotificationDependenciesManager;

/**
 * For a model notifications can depend on, such as an order. Deleting it
 * deletes every database notification that depends on it. Required for
 * dependencies unless pharaonic.notification-dependencies.all_models is true;
 * the dependentNotifications() relation always needs it.
 */
trait HasDependentNotifications
{
    /**
     * Delete the dependent notifications when the model is deleted. A soft
     * delete counts unless delete_on_soft_delete is false.
     */
    public static function bootHasDependentNotifications(): void
    {
        static::deleted(function (Model $model) {
            NotificationDependenciesManager::purge($model);
        });
    }

    /**
     * The stored database notifications that depend on this model.
     *
     * @return MorphToMany<DatabaseNotification, $this>
     */
    public function dependentNotifications(): MorphToMany
    {
        // The connection links are written and purged on, whatever this
        // model's own connection is.
        $notification = NotificationDependenciesManager::notificationModel();

        $relation = new DependentNotificationsMorphToMany(
            $notification->newQuery(),
            $this,
            'dependable',
            (new NotificationDependency)->getTable(),
            'dependable_id',
            'notification_id',
            $this->getKeyName(),
            $notification->getKeyName(),
            'dependentNotifications'
        );

        return $relation;
    }
}
