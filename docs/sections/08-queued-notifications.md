## Queued Notifications

Dependencies work the same for notifications that implement `ShouldQueue`.

```php title="app/Notifications/OrderShipped.php"
use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependencies;

class OrderShipped extends Notification implements ShouldQueue
{
    use HasDependencies;
    use Queueable;

    protected $reference;

    public function __construct(Order $order)
    {
        $this->reference = $order->reference;
        $this->dependsOn($order);
    }

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return ['message' => "Order {$this->reference} has shipped."];
    }
}
```

### What Travels Through the Queue

`dependsOn()` keeps only each model's connection, morph type, class, and key, never the model. A queued notification carries no extra models, and the worker doesn't query them back.

### A Dependency Deleted While the Job Waits

When the worker stores the notification, the package checks that every dependency still exists. If one was deleted in the meantime, the new notification is deleted right after the database channel stores it. A dependency whose class was renamed or removed since it was queued counts as deleted too.

### Notifications That Keep Their Own Models

A notification that keeps its own models with `SerializesModels` (for example to return them from `dependencies()`) needs those models back when the job runs. When one of them is gone, the job fails as usual, before anything is stored. Use `dependsOn()` in the constructor, as above, when the notification doesn't need the models themselves.

### Failures

Like any exception from a `NotificationSent` listener, a linking failure stops Laravel's sending at that notifiable, and the notification row is deleted with its links. A queued notification is then retried as Laravel retries any failed job.
