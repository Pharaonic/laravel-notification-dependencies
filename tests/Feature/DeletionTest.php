<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Feature;

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pharaonic\Laravel\NotificationDependencies\Support\NotificationDependenciesManager;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Comment;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\DependentNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\PlainNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Order;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Product;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Tag;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\User;
use Pharaonic\Laravel\NotificationDependencies\Tests\TestCase;

class DeletionTest extends TestCase
{
    public function test_deleting_the_only_dependency_deletes_the_notification(): void
    {
        $user = User::create();
        $order = Order::create();
        $user->notify((new DependentNotification)->dependsOn($order));

        $order->delete();

        $this->assertSame(0, $user->notifications()->count());
        $this->assertSame(0, $this->dependencyCount());
    }

    public function test_deleting_any_one_dependency_deletes_the_notification_and_all_its_links(): void
    {
        $user = User::create();
        $order = Order::create();
        $comment = Comment::create();
        $user->notify((new DependentNotification)->dependsOn($order, $comment));

        $comment->delete();

        $this->assertSame(0, $this->notificationCount());
        $this->assertSame(0, $this->dependencyCount());
        $this->assertTrue(Order::query()->whereKey($order->getKey())->exists(), 'The other dependency must not be deleted.');
    }

    public function test_notifications_that_do_not_depend_on_the_deleted_model_survive(): void
    {
        $user = User::create();
        $order = Order::create();
        $comment = Comment::create();

        $user->notify((new DependentNotification)->dependsOn($order, $comment)); // A
        $user->notify((new DependentNotification)->dependsOn($order));           // B
        $user->notify(new PlainNotification);

        $comment->delete();

        $this->assertSame(2, $this->notificationCount());
        $this->assertSame(1, $this->dependencyCount());

        $order->delete();

        $this->assertSame([PlainNotification::class], $user->notifications()->pluck('type')->all());
        $this->assertSame(0, $this->dependencyCount());
    }

    public function test_deleting_a_dependable_model_without_links_only_looks_them_up(): void
    {
        $user = User::create();
        $user->notify((new DependentNotification)->dependsOn(Order::create()));
        $product = Product::create();
        $queries = [];

        DB::listen(function (QueryExecuted $query) use (&$queries) {
            $queries[] = strtolower(ltrim($query->sql));
        });

        $product->delete();

        $this->assertCount(2, $queries, 'The product delete and one dependency lookup.');
        $this->assertTrue(Str::startsWith($queries[0], 'delete'));
        $this->assertTrue(Str::startsWith($queries[1], 'select'));
        $this->assertSame(1, $this->notificationCount());
        $this->assertSame(1, $this->dependencyCount());
    }

    public function test_deleting_a_model_without_the_trait_runs_no_package_query(): void
    {
        $user = User::create();
        $user->notify((new DependentNotification)->dependsOn(Order::create()));
        $other = User::create();
        $queries = 0;

        DB::listen(function () use (&$queries) {
            $queries++;
        });

        $other->delete();

        $this->assertSame(1, $queries);
        $this->assertSame(1, $this->notificationCount());
    }

    public function test_a_model_of_another_type_with_the_same_id_does_not_match(): void
    {
        $user = User::create();
        $order = Order::create();
        $comment = Comment::create();
        $this->assertEquals($order->getKey(), $comment->getKey());

        $user->notify((new DependentNotification)->dependsOn($order));

        $comment->delete();

        $this->assertSame(1, $this->notificationCount());
    }

    public function test_a_shared_dependency_deletes_every_dependent_notification_of_every_recipient(): void
    {
        $alice = User::create();
        $bob = User::create();
        $order = Order::create();

        $alice->notify((new DependentNotification)->dependsOn($order));
        $alice->notify((new DependentNotification)->dependsOn($order, Comment::create()));
        $bob->notify((new DependentNotification)->dependsOn($order));

        $order->delete();

        $this->assertSame(0, $this->notificationCount());
        $this->assertSame(0, $this->dependencyCount());
    }

    public function test_deleting_a_stored_notification_cascades_to_its_links(): void
    {
        $user = User::create();
        $order = Order::create();
        $user->notify((new DependentNotification)->dependsOn($order, Comment::create()));
        $user->notify((new DependentNotification)->dependsOn(Order::create()));

        $order->dependentNotifications()->firstOrFail()->delete();
        $this->assertSame(1, $this->notificationCount());
        $this->assertSame(1, $this->dependencyCount());

        $user->notifications()->delete();
        $this->assertSame(0, $this->dependencyCount());
    }

    public function test_repeated_and_duplicate_cleanup_is_harmless(): void
    {
        $user = User::create();
        $order = Order::create();
        $user->notify((new DependentNotification)->dependsOn($order));

        $order->delete();
        $order->delete();

        NotificationDependenciesManager::purge($order);
        NotificationDependenciesManager::purge($order);

        $this->assertSame(0, $this->notificationCount());
    }

    public function test_a_large_fan_out_is_deleted_in_bounded_batches(): void
    {
        $order = Order::create();
        $user = User::create();
        $identity = NotificationDependenciesManager::identify($order);
        $total = NotificationDependenciesManager::BATCH_SIZE * 2 + 7;
        $now = Carbon::now()->toDateTimeString();

        $kept = (string) Str::uuid();
        $notifications = [];
        $links = [];

        foreach (range(0, $total) as $i) {
            $id = $i === $total ? $kept : (string) Str::uuid();
            $notifications[] = [
                'id' => $id,
                'type' => DependentNotification::class,
                'notifiable_type' => User::class,
                'notifiable_id' => $user->getKey(),
                'data' => '{}',
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if ($i !== $total) {
                $links[] = [
                    'notification_id' => $id,
                    'dependable_type' => $identity['type'],
                    'dependable_id' => $identity['key'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($notifications, 100) as $chunk) {
            DB::table('notifications')->insert($chunk);
        }

        foreach (array_chunk($links, 100) as $chunk) {
            DB::table($this->dependencyTable())->insert($chunk);
        }

        $lookups = 0;
        $largestInList = 0;

        DB::listen(function (QueryExecuted $query) use (&$lookups, &$largestInList) {
            $sql = strtolower($query->sql);

            if (Str::startsWith($sql, 'select') && Str::contains($sql, $this->dependencyTable())) {
                $lookups++;
            }

            if (Str::startsWith($sql, 'delete')) {
                $largestInList = max($largestInList, count($query->bindings));
            }
        });

        $order->delete();

        $this->assertSame(3, $lookups);
        $this->assertSame(NotificationDependenciesManager::BATCH_SIZE, $largestInList);
        $this->assertSame([$kept], DB::table('notifications')->pluck('id')->all());
        $this->assertSame(0, $this->dependencyCount());
    }

    public function test_a_bulk_query_builder_delete_does_not_trigger_cleanup(): void
    {
        $user = User::create();
        $order = Order::create();
        $user->notify((new DependentNotification)->dependsOn($order));

        Order::query()->whereKey($order->getKey())->delete();

        $this->assertSame(1, $this->notificationCount(), 'Builder deletes fire no model events; this is documented.');
    }

    public function test_all_models_turned_on_after_boot_also_purges(): void
    {
        config(['pharaonic.notification-dependencies.all_models' => true]);

        $tag = Tag::create();
        User::create()->notify((new DependentNotification)->dependsOn($tag));
        $this->assertSame(1, $this->notificationCount());

        $tag->delete();

        $this->assertSame(0, $this->notificationCount(), 'dependsOn() accepted the tag, so its deletion must purge.');
    }
}
