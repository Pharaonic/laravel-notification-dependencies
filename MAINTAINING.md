# Maintaining This Package

This file records package-specific maintainer facts that are safe to keep in the public repository.

## Purpose

Deletes Laravel database notifications when any Eloquent model they depend on is deleted: `HasDependencies` on the notification, `HasDependentNotifications` on the model (optional with `all_models`), one `notification_dependencies` table linked to `notifications`, and its `NotificationDependency` model.

## Supported Laravel lines

One branch per Laravel major, from `6.x` to the newest major. Each line's PHP range, `illuminate/*` constraint, and tools follow the Pharaonic Laravel line table. `6.x` runs PHPUnit; later lines run Pest.

## Core library

None. Everything the package does is Laravel notification and Eloquent glue.

## Public API invariants

Configuration keys (`pharaonic.notification-dependencies.*`), publish tags, and registered Blade, Artisan, macro, and validation names are public API.

- Configuration: `pharaonic.notification-dependencies.table` (default `notification_dependencies`), `pharaonic.notification-dependencies.all_models` (default `false`, read on every use), and `pharaonic.notification-dependencies.delete_on_soft_delete` (default `true`).
- Publish tags: `pharaonic`, `laravel-notification-dependencies`, `pharaonic-config`, `notification-dependencies-config`, `pharaonic-migrations`, and `notification-dependencies-migrations`.
- `HasDependencies`: `dependsOn(Model ...$models): static` and `dependencies(): array`. Its `$notificationDependencies` property is internal.
- `HasDependentNotifications`: `dependentNotifications(): MorphToMany` of `Illuminate\Notifications\DatabaseNotification`.
- `dependsOn()` and `dependencies()` throw `InvalidArgumentException` for a model without `HasDependentNotifications` (unless `all_models`), an unsaved model, a key that is not an integer or non-empty string, or a value longer than its column.
- `Models\NotificationDependency` with `notification()`, `dependable()`, `scopeWhereDependable()`, the constant `DEFAULT_TABLE`, and the columns `notification_id`, `dependable_type`, `dependable_id`. The model only maps the table.
- Internal: `Support\NotificationDependenciesManager` (the entry point: identities, config, shared queries, `dependenciesExist()`, `link()` and `purge()`), `Actions\CheckDependencies`, `Actions\LinkDependencies` and `Actions\PurgeDependentNotifications` (one operation each, called through the manager), `Listeners\*`, and `Relations\*`.

## Package-specific constraints

- The migration file name `2026_11_06_000001_create_pharaonic_notification_dependencies_table.php` and its class `CreatePharaonicNotificationDependenciesTable` never change. The class is named because `6.x` supports Laravel below 8.37.
- Later schema changes go in new migrations, never in the released one. The migration refuses to run without the `notifications` table or over an existing dependency table.
- A link is identified by (`dependable_type`, `dependable_id`): unique with `notification_id`, indexed for the lookup on deletion. The connection is not stored (decided 2026-11-06); separating models of one class on several connections needs a new column in a new migration. The identity keeps the class and connection in memory only, to check each dependency exists on its own connection.
- Before each send, a synchronous `NotificationSending` listener reads the dependency rows (`Actions\CheckDependencies`, unlocked) and returns `false` when one is gone, so no channel sends it, queued or not. It returns `null` otherwise, because Laravel's `until()` stops at the first non-null listener response. It runs once per notifiable and channel and is never cached, so a dependency deleted between two channels stops the later ones.
- Registration happens in a synchronous `NotificationSent` listener, because `DatabaseChannel::send()` returns the stored row only there. Links are inserted, then the dependency rows are read again `FOR UPDATE` (selected, not counted: PostgreSQL does not lock aggregates). A concurrent delete either commits first and the check sees it, or waits for the links to commit and its purge sees them. The lock only orders dependencies on the notifications connection. When fewer rows than keys come back, each key is checked alone, so a case-insensitive collation matching two keys to one row is not a missing dependency. A dependency class that no longer exists counts as deleted, without an exception. On any other failure the new row is deleted and the exception rethrown; the listener does not swallow it, since how sending and queue retries react to an exception is Laravel's behavior.
- The package works on one connection: the default connection of `DatabaseNotification`, from `NotificationDependenciesManager::notificationModel()`. Linking rejects a notification stored elsewhere, purging and `dependentNotifications()` read there.
- Identity rules live in `NotificationDependenciesManager::resolve()` only: `identify()` throws its reason, `identityOf()` returns null, so linking and purging never disagree.
- Deletion uses each model's own `deleted` event through the trait. An `eloquent.deleted: *` wildcard is always registered and reads `all_models` on each deletion, like `dependsOn()`, so a change after boot applies to both; it skips models with the trait so nothing is purged twice. Trait checks are cached per class. Laravel 6 fires `deleted` for `forceDelete()` with `isForceDeleting()` true; there is no reliance on `forceDeleted`.
- Writes and deletes are set-based, at most 500 ids per statement, and never hydrate notifications.
- Ids are stored as strings (`varchar(191)`), so `DependentNotificationsMorphToMany` binds and compares the parent key as a string in lazy loads, eager loads, `has()`, `whereHas()`, and `withCount()`: PostgreSQL refuses `varchar = integer`. MySQL uses `CONCAT()` rather than `CAST()`, which fails when the connection collation differs from the table's.
- `phpstan.neon.dist` also analyses `tests/Fixtures`: PHPStan analyses a trait only through a class that uses it.
- `databases.yml` runs the suite against MySQL and PostgreSQL through the `DEPENDENCIES_DB*` environment variables.

## Branch notes

Fixes land on the oldest affected line and are ported to every later line and `develop`.

## Documentation

https://pharaonic.dev/packages/laravel/notification-dependencies

Keep this file factual and package-specific. General Pharaonic engineering policy belongs in the private engineering repository, not here.
