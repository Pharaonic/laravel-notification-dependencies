## Dependable Models

`HasDependentNotifications` does two things for a model: it deletes the dependent notifications when the model is deleted, and it adds the `dependentNotifications()` relation.

```php title="app/Models/Order.php"
use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependentNotifications;

class Order extends Model
{
    use HasDependentNotifications;
}
```

### Cleanup on Deletion

The trait listens to the model's own `deleted` event. When notifications depend on the model, they are deleted with set-based queries in batches of 500 ids, without loading them, and their links go with them.

```php
$order->delete(); // every notification that depends on this order is gone
```

:::warning Bulk deletes
`Order::where(...)->delete()`, raw SQL, and database cascades fire no Eloquent events, so their notifications stay. Delete such models one by one when their notifications must go: `Order::where(...)->get()->each->delete()`.
:::

### The dependentNotifications Relation

`dependentNotifications()` is a many-to-many relation to Laravel's `Illuminate\Notifications\DatabaseNotification`, through the dependency table. Use it like any Eloquent relation:

```php
$order->dependentNotifications;                       // Collection of DatabaseNotification

$order->dependentNotifications()->whereNull('read_at')->count();

Order::with('dependentNotifications')->get();

Order::withCount('dependentNotifications')->get();    // dependent_notifications_count

Order::whereHas('dependentNotifications', function ($query) {
    $query->whereNull('read_at');
})->get();
```

The relation works with integer, UUID, and ULID keys on MySQL, PostgreSQL, and SQLite: the dependency table stores ids as strings, and the relation compares the model's key as a string.

:::info With all_models
When `all_models` is on, models without the trait can be dependencies and are cleaned up on deletion, but only models with the trait have `dependentNotifications()`. Query the others through the [NotificationDependency model](#notification-dependency-model).
:::
