## API Reference

Every public name of the package. Classes in `Support`, `Actions`, `Listeners`, and `Relations` are internal.

### HasDependencies

`Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependencies`, for notifications.

| Method | Returns | Description |
| --- | --- | --- |
| `dependsOn(Model ...$models)` | `static` | Adds models the notification depends on. Duplicates are ignored. Throws `InvalidArgumentException` for a model that can't be a dependency. |
| `dependencies()` | `array` | Override to return the models the notification always depends on. Defaults to `[]`. |

### HasDependentNotifications

`Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependentNotifications`, for dependable models.

| Method | Returns | Description |
| --- | --- | --- |
| `dependentNotifications()` | `MorphToMany` | The stored `DatabaseNotification` rows that depend on the model. |

Deleting the model (or soft deleting it, unless `delete_on_soft_delete` is `false`) deletes those notifications.

### NotificationDependency

`Pharaonic\Laravel\NotificationDependencies\Models\NotificationDependency`

| Member | Type | Description |
| --- | --- | --- |
| `notification()` | `BelongsTo` | The stored database notification. |
| `dependable()` | `MorphTo` | The model the notification depends on. |
| `whereDependable(Model $model)` | scope | Only the links to this model. |
| `DEFAULT_TABLE` | constant | `notification_dependencies`, used when `table` is not set. |
| `notification_id`, `dependable_type`, `dependable_id` | columns | See [NotificationDependency Model](#notification-dependency-model). |

### Configuration Keys

| Key | Default |
| --- | --- |
| `pharaonic.notification-dependencies.table` | `notification_dependencies` |
| `pharaonic.notification-dependencies.all_models` | `false` |
| `pharaonic.notification-dependencies.delete_on_soft_delete` | `true` |

### Publish Tags

| Tag | Publishes |
| --- | --- |
| `notification-dependencies-config` | `config/pharaonic/notification-dependencies.php` |
| `notification-dependencies-migrations` | The migration, to `database/migrations` |
| `laravel-notification-dependencies` | This package's configuration and migration |
| `pharaonic-config` | The configuration, shared tag with other Pharaonic packages |
| `pharaonic-migrations` | The migration, shared tag with other Pharaonic packages |
| `pharaonic` | Configuration and migration, shared tag with other Pharaonic packages |

### Exceptions

| Exception | When |
| --- | --- |
| `InvalidArgumentException` | A dependency doesn't use `HasDependentNotifications` (and `all_models` is off), is unsaved, has no integer or non-empty string key, or has a key or morph type longer than 191 characters. |
| `InvalidArgumentException` | `dependencies()` returns something other than an array of Eloquent models. |
| `InvalidArgumentException` | The notification was stored on another connection than the default one of `DatabaseNotification`. |
| `RuntimeException` | The database channel didn't return the stored notification, so it can't be linked. |
| `RuntimeException` | The migration runs without the `notifications` table, or over an existing dependency table. |
