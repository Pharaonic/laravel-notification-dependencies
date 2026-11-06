## Basic Usage

Using the package takes two traits: one on the models a notification can depend on, and one on the notification.

### 1. Mark the Dependable Models

Add `HasDependentNotifications` to every model a notification can depend on:

```php title="app/Models/Order.php"
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependentNotifications;

class Order extends Model
{
    use HasDependentNotifications;
}
```

You can skip this step by turning on [`all_models`](#configuration).

### 2. Add HasDependencies to the Notification

```php title="app/Notifications/OrderCommented.php"
namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependencies;

class OrderCommented extends Notification
{
    use HasDependencies;

    public function via($notifiable)
    {
        return ['database'];
    }

    public function toDatabase($notifiable)
    {
        return ['message' => 'Your order received a comment.'];
    }
}
```

### 3. Name the Dependencies When You Send It

```php
$user->notify((new OrderCommented)->dependsOn($order));

$user->notify((new OrderCommented)->dependsOn($order, $comment));
```

### 4. Delete a Dependency

A notification is deleted as soon as **any** of its dependencies is deleted:

```text title="What gets deleted" no-copy
Notification A depends on Order #123 and Comment #456
Notification B depends on Order #123

$comment->delete();   // deletes A and its links; B stays
$order->delete();     // deletes B
```

Every recipient gets their own stored row, and each row gets its own links, so this works the same for `Notification::send($users, ...)`:

```php
use Illuminate\Support\Facades\Notification;

Notification::send($order->watchers, (new OrderCommented)->dependsOn($order));

$order->delete(); // deletes the notification of every watcher
```

:::info What is untouched
The dependency models themselves are never changed, and neither are the other channels of the notification. Only rows stored by the `database` channel are linked and deleted.
:::

A notification without `HasDependencies`, or with the trait but without any dependency, is stored exactly as Laravel stores it.
