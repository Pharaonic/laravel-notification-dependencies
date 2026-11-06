<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Feature;

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Comment;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\DependentNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Order;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\UlidModel;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\User;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\UuidModel;
use Pharaonic\Laravel\NotificationDependencies\Tests\TestCase;

class IdentityTest extends TestCase
{
    public function test_uuid_keys(): void
    {
        $user = User::create();
        [$deleted, $kept] = [UuidModel::create(), UuidModel::create()];
        $user->notify((new DependentNotification)->dependsOn($deleted));
        $user->notify((new DependentNotification)->dependsOn($kept));

        $this->assertSame([(string) $deleted->getKey(), (string) $kept->getKey()], DB::table($this->dependencyTable())->orderBy('id')->pluck('dependable_id')->all());

        $deleted->delete();

        $this->assertSame(1, $this->notificationCount());
        $this->assertSame([(string) $kept->getKey()], DB::table($this->dependencyTable())->pluck('dependable_id')->all());
    }

    public function test_ulid_keys(): void
    {
        $user = User::create();
        [$deleted, $kept] = [UlidModel::create(), UlidModel::create()];
        $user->notify((new DependentNotification)->dependsOn($deleted));
        $user->notify((new DependentNotification)->dependsOn($kept));

        $this->assertSame(26, strlen((string) $deleted->getKey()));

        $deleted->delete();

        $this->assertSame(1, $this->notificationCount());
    }

    public function test_an_integer_key_matches_its_string_form(): void
    {
        $user = User::create();
        $order = Order::create();
        $user->notify((new DependentNotification)->dependsOn($order));

        $reloaded = Order::query()->findOrFail($order->getKey());
        $reloaded->setAttribute('id', (string) $reloaded->getKey());
        $reloaded->syncOriginal();
        $reloaded->delete();

        $this->assertSame(0, $this->notificationCount());
    }

    public function test_morph_map_aliases_are_stored_and_matched(): void
    {
        Relation::morphMap(['order' => Order::class, 'comment' => Comment::class]);

        $user = User::create();
        $order = Order::create();
        $user->notify((new DependentNotification)->dependsOn($order));

        $this->assertSame(['order'], DB::table($this->dependencyTable())->pluck('dependable_type')->all());

        $order->delete();

        $this->assertSame(0, $this->notificationCount());
    }

    public function test_a_dependency_on_another_connection_is_checked_there_and_cleaned_up(): void
    {
        DB::connection('other')->getSchemaBuilder()->create('comments', function ($table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->timestamps();
        });

        $user = User::create();
        $elsewhere = (new Comment)->setConnection('other');
        $elsewhere->save();

        $user->notify((new DependentNotification)->dependsOn($elsewhere));

        $this->assertSame(1, $this->notificationCount(), 'The dependency was found on its own connection.');
        $this->assertSame(1, $elsewhere->dependentNotifications()->count(), 'The relation reads the links where they are.');

        $elsewhere->delete();

        $this->assertSame(0, $this->notificationCount());
    }

    public function test_a_notification_stored_on_another_connection_is_rejected(): void
    {
        $other = DB::connection('other')->getSchemaBuilder();
        $other->create('users', function ($table) {
            $table->bigIncrements('id');
            $table->timestamps();
        });
        $other->create('notifications', function ($table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        $user = (new User)->setConnection('other');
        $user->save();

        try {
            $user->notify((new DependentNotification)->dependsOn(Order::create()));
            $this->fail('A notification its purge could never find was accepted.');
        } catch (InvalidArgumentException $e) {
            $this->assertStringContainsString('stored on the [other] connection', $e->getMessage());
        }

        $this->assertSame(0, DB::connection('other')->table('notifications')->count());
        $this->assertSame(0, $this->dependencyCount());
    }

    public function test_keys_the_column_collation_treats_as_equal_both_exist(): void
    {
        if (DB::connection()->getDriverName() !== 'mysql') {
            $this->markTestSkipped('Needs a case-insensitive collation.');
        }

        $stored = UuidModel::create(['id' => 'abc']);
        $sameRow = (new UuidModel)->forceFill(['id' => 'ABC']);
        $sameRow->exists = true;

        User::create()->notify((new DependentNotification)->dependsOn($stored, $sameRow));

        $this->assertSame(1, $this->notificationCount(), 'One row matching two keys is not a missing dependency.');
    }

    public function test_a_model_without_an_explicit_connection_uses_the_default_connection_name(): void
    {
        $user = User::create();
        $loaded = Order::query()->findOrFail(Order::create()->getKey());
        $created = (new Order)->forceFill(['id' => $loaded->getKey()]);
        $created->exists = true;
        $this->assertNull($created->getConnectionName());
        $this->assertSame('testing', $loaded->getConnectionName());

        $user->notify((new DependentNotification)->dependsOn($created, $loaded));

        $this->assertSame(1, $this->dependencyCount());

        $loaded->delete();

        $this->assertSame(0, $this->notificationCount());
    }
}
