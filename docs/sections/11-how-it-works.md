## How It Works

You don't need this section to use the package, but it explains the guarantees and the limits.

### Sending

Laravel dispatches `NotificationSending` before each channel sends, once per notifiable and channel. A synchronous listener reads the dependency rows; when one is gone, it returns `false` and Laravel skips that channel. Every channel of the notification is skipped this way, queued or not. A notification without `HasDependencies` is never checked, and a notification whose dependencies all exist is left to your other `NotificationSending` listeners.

### Linking

Laravel's database channel stores the notification and returns the row, then dispatches `NotificationSent`. A synchronous listener links that row to its dependencies:

1. It inserts one link per dependency, inside a transaction (a savepoint when one is already open).
2. It reads every dependency row with a lock (`FOR UPDATE`) to check that it still exists.
3. When one is gone, the new notification is deleted.

Because the links are inserted before the lock, a deletion at the same moment either commits first, so the check sees the dependency gone, or waits for the links to commit, so its cleanup sees them. The check is a plain table query, so a global scope (a tenant or visibility scope) can't make an existing dependency look deleted.

When linking fails for any other reason, the notification and its links are deleted and the exception is rethrown, so a notification is never left with incomplete links.

### Deleting

Deleting a model with `HasDependentNotifications` (or any model, with `all_models`) runs one indexed lookup on `(dependable_type, dependable_id)`. When notifications depend on it, they are deleted with set-based queries in batches of 500 ids, without loading them. The links are deleted explicitly and through the foreign key.

### Why a Separate Table

A `subject_type` and `subject_id` pair on `notifications` would allow only one dependency per notification. A separate table lets one notification depend on several models of different types, and leaves Laravel's `notifications` table untouched.

### Limitations

- **Bulk deletes are not seen.** Query builder deletes, raw SQL, and database cascades fire no Eloquent events, so their notifications stay.
- **The database channel only**, on the default connection of `DatabaseNotification`. A notifiable with its own connection stores its notifications where the cleanup would never find them, so such a notification with dependencies is deleted and `InvalidArgumentException` is thrown.
- **One identity per type and key.** A link stores the morph type and key only, so the same model class on two connections (a database per tenant, for example) shares links: deleting `Order #1` on one connection deletes the notifications of `Order #1` on the other. Dependencies on another connection work otherwise, but the cleanup is not atomic across connections, and the lock only covers dependencies on the notifications connection.
- **A short window.** The notification row exists for a moment before its links do. A notification read in that window, or a dependency deleted on another connection at the same time, is not covered by a transaction across both.
- **SQLite** enforces the cascade only with `foreign_key_constraints` enabled on the connection. The package deletes links explicitly when it deletes notifications itself.

:::warning Laravel 10
Laravel 10 no longer receives security fixes upstream. Plan an upgrade to a supported Laravel line.
:::
