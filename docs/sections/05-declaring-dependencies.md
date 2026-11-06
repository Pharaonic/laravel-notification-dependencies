## Declaring Dependencies

`HasDependencies` gives a notification two ways to name its dependencies. You can use either one, or both together.

### At Send Time with dependsOn()

`dependsOn()` takes any number of models and returns the notification, so you can chain it. Repeated calls add to the set, and a model that is already in it is ignored:

```php
$notification = (new OrderCommented)
    ->dependsOn($order)
    ->dependsOn($comment, $product)
    ->dependsOn($order); // already there, ignored

$user->notify($notification);
```

`dependsOn()` without arguments does nothing, so you can spread an array that may be empty:

```php
$notification->dependsOn(...$optionalDependencies);
```

### In the Class with dependencies()

Override `dependencies()` to declare the models the notification always depends on. They are added to whatever `dependsOn()` received, without duplicates:

```php title="app/Notifications/OrderCommented.php"
use App\Models\Comment;
use App\Models\Order;
use Illuminate\Notifications\Notification;
use Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependencies;

class OrderCommented extends Notification
{
    use HasDependencies;

    protected $order;

    protected $comment;

    public function __construct(Order $order, Comment $comment, array $dependencies = [])
    {
        $this->order = $order;
        $this->comment = $comment;
        $this->dependsOn(...$dependencies);
    }

    public function dependencies(): array
    {
        return [$this->order, $this->comment];
    }

    // via() and toDatabase()...
}
```

`dependencies()` is read when the database channel stores the notification, so it sees the models the notification holds at that moment. It must return an array of Eloquent models.

### Calling dependsOn() in the Constructor

You can also call `dependsOn()` from the constructor. This keeps only the identities, not the models, which is what you want for a queued notification that doesn't need the models itself:

```php title="app/Notifications/InvoiceIssued.php"
public function __construct(Invoice $invoice)
{
    $this->number = $invoice->number;
    $this->dependsOn($invoice);
}
```

See [Queued Notifications](#queued-notifications).

### What Can Be a Dependency

`dependsOn()` and `dependencies()` accept a model only when:

- it uses `HasDependentNotifications`, or `all_models` is on;
- it is saved (`$model->exists` is true);
- its primary key is an integer or a non-empty string, at most 191 characters;
- its morph type is at most 191 characters.

Anything else throws `InvalidArgumentException` with the reason, so a dependency can never be forgotten silently. `dependsOn()` throws right away; `dependencies()` throws when the notification is stored, and the stored row is deleted first.

Models are identified by their morph type (morph map aliases included) and primary key. Integer, UUID, and ULID keys share the same table.
