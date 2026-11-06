<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use Orchestra\Testbench\TestCase as Orchestra;
use Pharaonic\Laravel\NotificationDependencies\Models\NotificationDependency;
use Pharaonic\Laravel\NotificationDependencies\NotificationDependenciesServiceProvider;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Schema;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Relation::$morphMap = [];
        $this->freshDatabase();
    }

    protected function tearDown(): void
    {
        Relation::$morphMap = [];

        parent::tearDown();
    }

    protected function getPackageProviders($app)
    {
        return [NotificationDependenciesServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', self::connection());
        $app['config']->set('database.connections.other', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
        $app['config']->set('queue.default', 'sync');
        $app['config']->set('queue.connections.database', [
            'driver' => 'database',
            'table' => 'jobs',
            'queue' => 'default',
            'retry_after' => 90,
        ]);
        // Laravel 8+ defaults to database-uuids, which needs a uuid column.
        $app['config']->set('queue.failed.driver', 'database');
        $app['config']->set('queue.failed.database', 'testing');
        $app['config']->set('mail.driver', 'array');
    }

    /**
     * The test database: in-memory SQLite with foreign keys enforced, unless
     * DEPENDENCIES_DB selects mysql or pgsql.
     *
     * @return array<string, mixed>
     */
    public static function connection(): array
    {
        $env = static function (string $name, string $default): string {
            $value = getenv($name);

            return is_string($value) && $value !== '' ? $value : $default;
        };

        switch ($env('DEPENDENCIES_DB', 'sqlite')) {
            case 'mysql':
                return [
                    'driver' => 'mysql',
                    'host' => $env('DEPENDENCIES_DB_HOST', '127.0.0.1'),
                    'port' => $env('DEPENDENCIES_DB_PORT', '3306'),
                    'database' => $env('DEPENDENCIES_DB_DATABASE', 'dependencies'),
                    'username' => $env('DEPENDENCIES_DB_USERNAME', 'root'),
                    'password' => $env('DEPENDENCIES_DB_PASSWORD', ''),
                    'charset' => 'utf8mb4',
                    'collation' => 'utf8mb4_unicode_ci',
                    'prefix' => '',
                    'strict' => true,
                    'engine' => 'InnoDB',
                ];
            case 'pgsql':
                return [
                    'driver' => 'pgsql',
                    'host' => $env('DEPENDENCIES_DB_HOST', '127.0.0.1'),
                    'port' => $env('DEPENDENCIES_DB_PORT', '5432'),
                    'database' => $env('DEPENDENCIES_DB_DATABASE', 'dependencies'),
                    'username' => $env('DEPENDENCIES_DB_USERNAME', 'postgres'),
                    'password' => $env('DEPENDENCIES_DB_PASSWORD', ''),
                    'charset' => 'utf8',
                    'prefix' => '',
                    'schema' => 'public',
                    'sslmode' => 'prefer',
                ];
            default:
                return [
                    'driver' => 'sqlite',
                    'database' => ':memory:',
                    'prefix' => '',
                    'foreign_key_constraints' => true,
                ];
        }
    }

    /**
     * Drop every table, create Laravel's notifications table and the
     * fixtures, then run the package migration the way an application does.
     */
    protected function freshDatabase(): void
    {
        $kernel = $this->app->make(Kernel::class);
        $kernel->call('db:wipe', ['--force' => true]);

        Schema::create();

        $kernel->call('migrate', ['--force' => true]);
    }

    protected function dependencyTable(): string
    {
        return (new NotificationDependency)->getTable();
    }

    protected function dependencyCount(): int
    {
        return DB::table($this->dependencyTable())->count();
    }

    protected function notificationCount(): int
    {
        return DB::table('notifications')->count();
    }
}
