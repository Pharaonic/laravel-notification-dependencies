## Troubleshooting

### "A notification can only depend on a model that uses HasDependentNotifications"

The model you passed to `dependsOn()`, or returned from `dependencies()`, doesn't use the trait. Add `HasDependentNotifications` to the model, or set `all_models` to `true` when you can't edit it.

### "A notification cannot depend on an unsaved model"

The model hasn't been saved yet, so it has no key to link. Save it before you call `dependsOn()`:

```php
$comment = Comment::create($data);   // not new Comment($data)

$user->notify((new OrderCommented)->dependsOn($comment));
```

### The migration fails with "needs the [notifications] table first"

Laravel's `notifications` table doesn't exist yet. Create it, then migrate again:

```bash title="Terminal" no-line-numbers
php artisan notifications:table
php artisan migrate
```

### The migration fails with "table already exists"

Another table already uses the name `notification_dependencies`. Publish the configuration, set `table` to another name, and migrate again.

### Notifications stay after a bulk delete

`Model::where(...)->delete()`, raw SQL, and database cascades fire no Eloquent events. Delete the models one by one:

```php
Order::where('status', 'cancelled')->get()->each->delete();
```

### Notifications stay after a soft delete

`delete_on_soft_delete` is `false`, so only `forceDelete()` removes them. Set it to `true` if a soft delete should count.

### Notifications stay after adding a morph map

Links store the morph type at the moment the notification is sent. When you add or change a morph map alias later, older links still hold the previous type and no longer match the model. Update `dependable_type` in the dependency table to the new alias when you change the morph map.

### The notification disappears right after it is sent

One of its dependencies no longer existed when the notification was stored, so it was deleted. This happens with queued notifications whose dependency was deleted while the job waited, and with dependencies that are already soft deleted while `delete_on_soft_delete` is `true`.

### "The notification was stored on the [...] connection"

The notifiable stores its notifications on a connection other than the default one of `DatabaseNotification`. The package only links and purges notifications on that default connection, so it deletes the notification and throws. Store notifications with dependencies on the default connection.

### Links remain on SQLite after deleting a notification yourself

SQLite enforces the cascading foreign key only when `foreign_key_constraints` is enabled on the connection. Enable it in `config/database.php`:

```php title="config/database.php"
'sqlite' => [
    // ...
    'foreign_key_constraints' => true,
],
```

The package already deletes the links explicitly whenever it deletes notifications itself.
