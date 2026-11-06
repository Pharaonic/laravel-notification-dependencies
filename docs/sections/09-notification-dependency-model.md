## NotificationDependency Model

`Pharaonic\Laravel\NotificationDependencies\Models\NotificationDependency` maps the dependency table: one row per stored notification and model it depends on. You don't need it for everyday use, but it lets you query the links directly.

### The Table

| Column | Type | Description |
| --- | --- | --- |
| `id` | big increments | Primary key. |
| `notification_id` | uuid | The `notifications.id` of the stored notification. Cascades on delete. |
| `dependable_type` | string (191) | The dependency's morph type (its morph map alias, or class name). |
| `dependable_id` | string (191) | The dependency's primary key, as a string. |
| `created_at`, `updated_at` | timestamps | When the link was written. |

`(notification_id, dependable_type, dependable_id)` is unique, and `(dependable_type, dependable_id)` is indexed for the lookup on deletion.

### Querying Links

```php
use Pharaonic\Laravel\NotificationDependencies\Models\NotificationDependency;

// Every link to this order, with its notification
NotificationDependency::whereDependable($order)->with('notification')->get();

// The models one notification depends on
NotificationDependency::where('notification_id', $notification->id)
    ->with('dependable')
    ->get()
    ->pluck('dependable');
```

`whereDependable()` accepts any model, with or without `HasDependentNotifications`. A model that could never have been linked (no usable key, for example) matches nothing.

### Relations

| Method | Returns | Description |
| --- | --- | --- |
| `notification()` | `BelongsTo` | The stored `Illuminate\Notifications\DatabaseNotification`. |
| `dependable()` | `MorphTo` | The model the notification depends on. |

:::info Writes
The package writes and deletes links itself. Deleting a notification deletes its links through the foreign key, so you don't need to manage rows of this table.
:::
