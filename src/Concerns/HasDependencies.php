<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Concerns;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\NotificationDependencies\Support\NotificationDependenciesManager;

/**
 * Lets a notification depend on Eloquent models. Its database notification
 * is deleted as soon as any one of them is deleted.
 *
 * @phpstan-import-type Identity from NotificationDependenciesManager
 */
trait HasDependencies
{
    /**
     * Identities added with dependsOn(), keyed by their signature. Plain arrays,
     * so a queued notification carries no extra model.
     *
     * @var array<string, Identity>
     */
    protected $notificationDependencies = [];

    /**
     * Add models this notification depends on. Repeated calls add to the set,
     * and a model already in it is ignored.
     *
     * @throws \InvalidArgumentException when a model cannot be a dependency
     */
    public function dependsOn(Model ...$models): static
    {
        foreach ($models as $model) {
            $identity = NotificationDependenciesManager::identify($model);

            $this->notificationDependencies[$identity['signature']] = $identity;
        }

        return $this;
    }

    /**
     * The models this notification always depends on. Override it to declare
     * them in the class; they are added to the ones given to dependsOn().
     *
     * @return array<int, Model>
     */
    public function dependencies(): array
    {
        return [];
    }
}
