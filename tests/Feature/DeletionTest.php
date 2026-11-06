<?php

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

test('deleting the only dependency deletes the notification', function () {
    $user = User::create();
    $order = Order::create();
    $user->notify((new DependentNotification)->dependsOn($order));

    $order->delete();

    $this->assertSame(0, $user->notifications()->count());
    $this->assertSame(0, $this->dependencyCount());
});

test('deleting any one dependency deletes the notification and all its links', function () {
    $user = User::create();
    $order = Order::create();
    $comment = Comment::create();
    $user->notify((new DependentNotification)->dependsOn($order, $comment));

    $comment->delete();

    $this->assertSame(0, $this->notificationCount());
    $this->assertSame(0, $this->dependencyCount());
    $this->assertTrue(Order::query()->whereKey($order->getKey())->exists(), 'The other dependency must not be deleted.');
});

test('notifications that do not depend on the deleted model survive', function () {
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
});

test('deleting a dependable model without links only looks them up', function () {
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
});

test('deleting a model without the trait runs no package query', function () {
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
});

test('a model of another type with the same id does not match', function () {
    $user = User::create();
    $order = Order::create();
    $comment = Comment::create();
    $this->assertEquals($order->getKey(), $comment->getKey());

    $user->notify((new DependentNotification)->dependsOn($order));

    $comment->delete();

    $this->assertSame(1, $this->notificationCount());
});

test('a shared dependency deletes every dependent notification of every recipient', function () {
    $alice = User::create();
    $bob = User::create();
    $order = Order::create();

    $alice->notify((new DependentNotification)->dependsOn($order));
    $alice->notify((new DependentNotification)->dependsOn($order, Comment::create()));
    $bob->notify((new DependentNotification)->dependsOn($order));

    $order->delete();

    $this->assertSame(0, $this->notificationCount());
    $this->assertSame(0, $this->dependencyCount());
});

test('deleting a stored notification cascades to its links', function () {
    $user = User::create();
    $order = Order::create();
    $user->notify((new DependentNotification)->dependsOn($order, Comment::create()));
    $user->notify((new DependentNotification)->dependsOn(Order::create()));

    $order->dependentNotifications()->firstOrFail()->delete();
    $this->assertSame(1, $this->notificationCount());
    $this->assertSame(1, $this->dependencyCount());

    $user->notifications()->delete();
    $this->assertSame(0, $this->dependencyCount());
});

test('repeated and duplicate cleanup is harmless', function () {
    $user = User::create();
    $order = Order::create();
    $user->notify((new DependentNotification)->dependsOn($order));

    $order->delete();
    $order->delete();

    NotificationDependenciesManager::purge($order);
    NotificationDependenciesManager::purge($order);

    $this->assertSame(0, $this->notificationCount());
});

test('a large fan out is deleted in bounded batches', function () {
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
});

test('a bulk query builder delete does not trigger cleanup', function () {
    $user = User::create();
    $order = Order::create();
    $user->notify((new DependentNotification)->dependsOn($order));

    Order::query()->whereKey($order->getKey())->delete();

    $this->assertSame(1, $this->notificationCount(), 'Builder deletes fire no model events; this is documented.');
});

test('all models turned on after boot also purges', function () {
    config(['pharaonic.notification-dependencies.all_models' => true]);

    $tag = Tag::create();
    User::create()->notify((new DependentNotification)->dependsOn($tag));
    $this->assertSame(1, $this->notificationCount());

    $tag->delete();

    $this->assertSame(0, $this->notificationCount(), 'dependsOn() accepted the tag, so its deletion must purge.');
});
