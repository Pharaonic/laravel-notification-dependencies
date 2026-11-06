## Examples

### 1. Comments on an Order

A customer is notified of each new comment on their order. The notification goes away when the comment is deleted, or when the whole order is.

- ===Models

  ```php title="app/Models/Order.php"
  namespace App\Models;

  use Illuminate\Database\Eloquent\Model;
  use Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependentNotifications;

  class Order extends Model
  {
      use HasDependentNotifications;

      public function customer()
      {
          return $this->belongsTo(User::class, 'user_id');
      }

      public function comments()
      {
          return $this->hasMany(Comment::class);
      }
  }
  ```

  ```php title="app/Models/Comment.php"
  namespace App\Models;

  use Illuminate\Database\Eloquent\Model;
  use Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependentNotifications;

  class Comment extends Model
  {
      use HasDependentNotifications;
  }
  ```

- ===Notification

  ```php title="app/Notifications/OrderCommented.php"
  namespace App\Notifications;

  use App\Models\Comment;
  use App\Models\Order;
  use Illuminate\Notifications\Notification;
  use Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependencies;

  class OrderCommented extends Notification
  {
      use HasDependencies;

      protected $order;

      protected $comment;

      public function __construct(Order $order, Comment $comment)
      {
          $this->order = $order;
          $this->comment = $comment;
      }

      public function dependencies(): array
      {
          return [$this->order, $this->comment];
      }

      public function via($notifiable)
      {
          return ['database', 'mail'];
      }

      public function toDatabase($notifiable)
      {
          return [
              'order_id' => $this->order->id,
              'comment_id' => $this->comment->id,
          ];
      }

      // toMail()...
  }
  ```

- ===Controller

  ```php title="app/Http/Controllers/OrderCommentController.php"
  namespace App\Http\Controllers;

  use App\Models\Comment;
  use App\Notifications\OrderCommented;
  use App\Models\Order;
  use Illuminate\Http\Request;

  class OrderCommentController extends Controller
  {
      public function store(Request $request, Order $order)
      {
          $comment = $order->comments()->create($request->validate([
              'body' => 'required|string',
          ]));

          $order->customer->notify(new OrderCommented($order, $comment));

          return back();
      }

      public function destroy(Order $order, Comment $comment)
      {
          $comment->delete(); // the customer's notification is deleted too

          return back();
      }
  }
  ```

The mail is sent once and stays in the inbox; only the stored database notification is removed.

### 2. A Friend Request That Is Answered

A pending friend request is deleted once it is accepted or declined. Its notification goes with it, so the recipient never sees a request they can no longer act on. `FriendRequest` uses `HasDependentNotifications`.

```php title="app/Http/Controllers/FriendRequestController.php"
use App\Models\FriendRequest;
use App\Notifications\FriendRequestReceived;

public function store(User $recipient)
{
    $request = FriendRequest::create([
        'sender_id' => auth()->id(),
        'recipient_id' => $recipient->id,
    ]);

    $recipient->notify((new FriendRequestReceived)->dependsOn($request));
}

public function accept(FriendRequest $request)
{
    auth()->user()->friends()->attach($request->sender_id);

    $request->delete(); // removes the "new friend request" notification
}
```

### 3. Unread Notifications per Project

Show, on a project list, how many unread notifications each project still has, using the relation from `HasDependentNotifications`.

```php title="app/Http/Controllers/ProjectController.php"
use App\Models\Project;

public function index()
{
    $projects = Project::withCount(['dependentNotifications as unread_count' => function ($query) {
        $query->whereNull('read_at')
            ->where('notifiable_id', auth()->id())
            ->where('notifiable_type', auth()->user()->getMorphClass());
    }])->get();

    return view('projects.index', compact('projects'));
}
```

```blade title="resources/views/projects/index.blade.php"
@foreach ($projects as $project)
    <a href="{{ route('projects.show', $project) }}">
        {{ $project->name }}
        @if ($project->unread_count)
            <span class="badge">{{ $project->unread_count }}</span>
        @endif
    </a>
@endforeach
```

### 4. A Model You Can't Edit

A notification depends on a model from another package, so you can't add the trait to it. Turn on `all_models`, and query its links through `NotificationDependency`:

```php title="config/pharaonic/notification-dependencies.php"
'all_models' => true,
```

```php
use Pharaonic\Laravel\NotificationDependencies\Models\NotificationDependency;

$user->notify((new SubscriptionRenewed)->dependsOn($subscription));

NotificationDependency::whereDependable($subscription)->count(); // 1

$subscription->delete(); // the notification is deleted
```

### 5. Keep Notifications of Archived Records

Tasks are soft deleted when archived and can be restored. Keep their notifications until a task is permanently removed:

```php title="config/pharaonic/notification-dependencies.php"
'delete_on_soft_delete' => false,
```

```php
$assignee->notify((new TaskAssigned)->dependsOn($task));

$task->delete();       // archived: the notification stays
$task->restore();      // back, with its notification
$task->forceDelete();  // gone for good, and so is the notification
```
