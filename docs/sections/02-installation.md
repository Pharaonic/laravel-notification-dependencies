## Installation

The package links rows of Laravel's own `notifications` table, so that table has to exist before you migrate.

### Requirements

- PHP 8.0, 8.1 or 8.2
- Laravel 9.33 or newer on the 9.x line
- The `notifications` table of Laravel's database channel

### Create the Notifications Table

Skip this step when your application already stores database notifications.

```bash title="Terminal" no-line-numbers
php artisan notifications:table
php artisan migrate
```

### Composer Installation

```bash title="Terminal" no-line-numbers
composer require {package.composer}
```

The service provider is registered automatically through package discovery.

### Run Migrations

The package loads its migration automatically, so a plain `migrate` creates the `notification_dependencies` table:

```bash title="Terminal" no-line-numbers
php artisan migrate
```

The migration stops with an explanation when the `notifications` table is missing, or when a table with the dependency table's name already exists.

### Publish Configuration

Publish the configuration only when you need to rename the table, accept any model as a dependency, or change the soft-delete behavior:

```bash title="Terminal" no-line-numbers
php artisan vendor:publish --tag=notification-dependencies-config
```

The file is published to `config/pharaonic/notification-dependencies.php`. See [Configuration](#configuration).

### Publish the Migration (Optional)

You don't need to publish the migration to run it. Publish it only when you want a copy in `database/migrations`:

```bash title="Terminal" no-line-numbers
php artisan vendor:publish --tag=notification-dependencies-migrations
```

:::tip Renaming the table
Set `table` in the published configuration **before** you run the migration. Changing it later does not rename an existing table.
:::

:::success Installation Complete
You're all set. Add `HasDependentNotifications` to your models and `HasDependencies` to your notifications, as shown in [Basic Usage](#basic-usage).
:::
