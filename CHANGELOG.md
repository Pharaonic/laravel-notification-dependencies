# Changelog

All notable changes to this project are documented here.

## 6.1.0 - 2026-11-12

### Changed

- A notification whose dependency no longer exists when it is sent is not sent on any channel. Before, only its database row was deleted after it was stored, and mail, broadcast, and other channels were still sent. This covers a dependency deleted while a queued notification waited, a row deleted without a model event, and a soft-deleted dependency while `delete_on_soft_delete` is `true`.

### Behavior Changes from 6.0.x

- Every channel of such a notification is skipped through a `NotificationSending` listener that returns `false`. Notifications without `HasDependencies` are not checked.
- `dependencies()` is also read before each channel sends, so an invalid return value throws before any channel sends.
- Sending a notification with dependencies runs one more query per notifiable and channel.

## 6.0.0 - 2026-11-07

### Added

- `HasDependencies` for notifications: `dependsOn(Model ...$models)` and an overridable `dependencies()`, merged without duplicates.
- `HasDependentNotifications` for dependable models: deleting the model deletes every database notification that depends on it, and `dependentNotifications()` returns them.
- The `NotificationDependency` model with `notification()` and `dependable()` relations and a `whereDependable()` scope.
- A `notification_dependencies` table linked to `notifications` with a cascading foreign key, unique per notification and dependency, with string ids so integer, UUID, and ULID keys share it.
- Queued, multi-recipient, and morph-mapped notifications; a dependency already deleted at delivery deletes the new notification.
- `pharaonic.notification-dependencies.table`, `all_models` (any model can be a dependency without the trait), and `delete_on_soft_delete` settings.

### Compatibility

- Laravel 6.20+ on PHP 8.0.
