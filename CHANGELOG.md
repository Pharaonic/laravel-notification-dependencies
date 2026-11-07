# Changelog

All notable changes to this project are documented here.

## 7.0.0 - 2026-11-07

First release of the Laravel 7 line, built from 6.0.0.

### Compatibility

- Laravel 7.30+ on PHP 8.0.
- The same API and behavior as 6.0.0.

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
