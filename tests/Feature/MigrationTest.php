<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Feature;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Order;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Schema as FixtureSchema;
use Pharaonic\Laravel\NotificationDependencies\Tests\TestCase;
use RuntimeException;

class MigrationTest extends TestCase
{
    public function test_migrate_creates_the_dependency_table(): void
    {
        $this->assertTrue(Schema::hasTable($this->dependencyTable()));
        $this->assertTrue(Schema::hasColumns($this->dependencyTable(), [
            'id', 'notification_id', 'dependable_type', 'dependable_id', 'created_at', 'updated_at',
        ]));
    }

    public function test_the_unique_index_rejects_a_second_identical_link(): void
    {
        $id = $this->storeNotification();
        $row = $this->link($id);

        DB::table($this->dependencyTable())->insert($row);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table($this->dependencyTable())->insert($row);
    }

    public function test_the_foreign_key_rejects_a_link_to_a_missing_notification(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table($this->dependencyTable())->insert($this->link('00000000-0000-0000-0000-000000000000'));
    }

    public function test_the_foreign_key_cascades_notification_deletes(): void
    {
        $id = $this->storeNotification();
        DB::table($this->dependencyTable())->insert($this->link($id));

        DB::table('notifications')->where('id', $id)->delete();

        $this->assertSame(0, $this->dependencyCount());
    }

    public function test_the_longest_accepted_values_fit(): void
    {
        $id = $this->storeNotification();
        $row = $this->link($id);
        $row['dependable_type'] = str_repeat('t', 191);
        $row['dependable_id'] = str_repeat('k', 191);

        DB::table($this->dependencyTable())->insert($row);

        $this->assertSame(1, $this->dependencyCount());
    }

    public function test_rollback_drops_the_table(): void
    {
        $this->app->make(Kernel::class)->call('migrate:rollback', ['--force' => true]);

        $this->assertFalse(Schema::hasTable($this->dependencyTable()));
        $this->assertTrue(Schema::hasTable('notifications'));
    }

    public function test_migrating_without_the_notifications_table_fails_with_instructions(): void
    {
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
    }

    public function test_migrating_over_an_existing_table_fails(): void
    {
        $kernel = $this->app->make(Kernel::class);
        $kernel->call('migrate:rollback', ['--force' => true]);
        Schema::create($this->dependencyTable(), function ($table) {
            $table->bigIncrements('id');
        });

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('already exists');

        $kernel->call('migrate', ['--force' => true]);
    }

    private function storeNotification(): string
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
    private function link(string $notificationId): array
    {
        return [
            'notification_id' => $notificationId,
            'dependable_type' => Order::class,
            'dependable_id' => '1',
        ];
    }
}
