<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Order;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Schema as FixtureSchema;

test('migrate creates the dependency table', function () {
    $this->assertTrue(Schema::hasTable($this->dependencyTable()));
    $this->assertTrue(Schema::hasColumns($this->dependencyTable(), [
        'id', 'notification_id', 'dependable_type', 'dependable_id', 'created_at', 'updated_at',
    ]));
});

test('the unique index rejects a second identical link', function () {
    $id = storeMigrationNotification();
    $row = migrationLink($id);

    DB::table($this->dependencyTable())->insert($row);

    $this->expectException(\Illuminate\Database\QueryException::class);

    DB::table($this->dependencyTable())->insert($row);
});

test('the foreign key rejects a link to a missing notification', function () {
    $this->expectException(\Illuminate\Database\QueryException::class);

    DB::table($this->dependencyTable())->insert(migrationLink('00000000-0000-0000-0000-000000000000'));
});

test('the foreign key cascades notification deletes', function () {
    $id = storeMigrationNotification();
    DB::table($this->dependencyTable())->insert(migrationLink($id));

    DB::table('notifications')->where('id', $id)->delete();

    $this->assertSame(0, $this->dependencyCount());
});

test('the longest accepted values fit', function () {
    $id = storeMigrationNotification();
    $row = migrationLink($id);
    $row['dependable_type'] = str_repeat('t', 191);
    $row['dependable_id'] = str_repeat('k', 191);

    DB::table($this->dependencyTable())->insert($row);

    $this->assertSame(1, $this->dependencyCount());
});

test('rollback drops the table', function () {
    $this->app->make(Kernel::class)->call('migrate:rollback', ['--force' => true]);

    $this->assertFalse(Schema::hasTable($this->dependencyTable()));
    $this->assertTrue(Schema::hasTable('notifications'));
});

test('migrating without the notifications table fails with instructions', function () {
    $kernel = $this->app->make(Kernel::class);
    $kernel->call('db:wipe', ['--force' => true]);

    try {
        $kernel->call('migrate', ['--force' => true]);
        $this->fail('The migration ran without a notifications table.');
    } catch (RuntimeException $e) {
        $this->assertStringContainsString('php artisan notifications:table', $e->getMessage());
    }

    $this->assertFalse(Schema::hasTable($this->dependencyTable()));

    FixtureSchema::notifications();
    $kernel->call('migrate', ['--force' => true]);

    $this->assertTrue(Schema::hasTable($this->dependencyTable()));
});

test('migrating over an existing table fails', function () {
    $kernel = $this->app->make(Kernel::class);
    $kernel->call('migrate:rollback', ['--force' => true]);
    Schema::create($this->dependencyTable(), function ($table) {
        $table->bigIncrements('id');
    });

    $this->expectException(RuntimeException::class);
    $this->expectExceptionMessage('already exists');

    $kernel->call('migrate', ['--force' => true]);
});

function storeMigrationNotification(): string
{
    $id = '11111111-1111-1111-1111-111111111111';

    DB::table('notifications')->insert([
        'id' => $id,
        'type' => 'Test',
        'notifiable_type' => Order::class,
        'notifiable_id' => 1,
        'data' => '{}',
    ]);

    return $id;
}

/**
 * @return array<string, string>
 */
function migrationLink(string $notificationId): array
{
    return [
        'notification_id' => $notificationId,
        'dependable_type' => Order::class,
        'dependable_id' => '1',
    ];
}
