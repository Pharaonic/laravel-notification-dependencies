## Soft Deletes

A dependency that uses `SoftDeletes` follows the [`delete_on_soft_delete`](#configuration) setting.

### Default: Soft Delete Removes the Notifications

```php
$order->delete();      // soft delete: the dependent notifications are deleted
$order->trashed();     // true
```

A model that is already soft deleted can't be a dependency in this mode: a notification that depends on it is deleted right after it is stored.

### Keep Them Until Force Delete

```php title="config/pharaonic/notification-dependencies.php"
'delete_on_soft_delete' => false,
```

```php
$order->delete();       // soft delete: the notifications stay
$order->restore();      // still there
$order->forceDelete();  // now they are deleted
```

In this mode a soft-deleted model still counts as existing, so a new notification can depend on it.

:::warning Restoring does not bring them back
With the default setting, restoring a soft-deleted model does not restore the notifications its soft delete removed. Use `delete_on_soft_delete => false` when restores are common in your application.
:::
