## Configuration

The defaults work without publishing anything. After `vendor:publish --tag=notification-dependencies-config`, the options live in `config/pharaonic/notification-dependencies.php` and are read under the `pharaonic.notification-dependencies` key.

```php title="config/pharaonic/notification-dependencies.php"
return [
    'table' => 'notification_dependencies',

    'all_models' => false,

    'delete_on_soft_delete' => true,
];
```

### Options

| Key | Default | Description |
| --- | --- | --- |
| `table` | `notification_dependencies` | The dependency table the migration creates and every query uses. |
| `all_models` | `false` | When `true`, any saved Eloquent model can be a dependency, without `HasDependentNotifications`. |
| `delete_on_soft_delete` | `true` | When `true`, soft deleting a dependency deletes its notifications. When `false`, only `forceDelete()` does. |

### table

The migration creates the table with this name, and `NotificationDependency` maps to it. Set it before migrating: changing it after the migration ran does not rename the existing table.

### all_models

By default, a notification can only depend on models that use `HasDependentNotifications`, and only their deletions are checked. Turn `all_models` on to accept any model, such as a model from another package you can't edit:

```php title="config/pharaonic/notification-dependencies.php"
'all_models' => true,
```

:::warning Performance
With `all_models` on, every Eloquent model deletion in the application runs one indexed lookup on the dependency table. Models that use `HasDependentNotifications` are still cleaned up once, through their own event, and only they have the `dependentNotifications()` relation.
:::

The setting is read on every use, so changing it at runtime (in a test, for example) applies to `dependsOn()` and to deletions alike.

### delete_on_soft_delete

By default, soft deleting a dependency deletes its notifications. Set it to `false` to keep them until the model is force deleted:

```php title="config/pharaonic/notification-dependencies.php"
'delete_on_soft_delete' => false,
```

See [Soft Deletes](#soft-deletes) for the details.
