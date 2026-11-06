<?php

use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\DependentNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Order;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Tag;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\User;

/**
 * With all_models on, any model can be a dependency without the trait.
 */
beforeEach(function () {
    config(['pharaonic.notification-dependencies.all_models' => true]);
});

test('the wildcard listener is registered', function () {
    $this->assertTrue($this->app['events']->hasWildcardListeners('eloquent.deleted: App\\Anything'));
});

test('turning it off after boot stops both sides', function () {
    config(['pharaonic.notification-dependencies.all_models' => false]);

    $this->expectException(\InvalidArgumentException::class);

    (new DependentNotification)->dependsOn(Tag::create());
});

test('a model without the trait can be a dependency', function () {
    $user = User::create();
    $tag = Tag::create();
    $order = Order::create();

    $user->notify((new DependentNotification)->dependsOn($tag, $order));

    $this->assertSame(2, $this->dependencyCount());

    $tag->delete();

    $this->assertSame(0, $this->notificationCount());
    $this->assertSame(0, $this->dependencyCount());
});

test('declared dependencies without the trait', function () {
    $user = User::create();
    $tag = Tag::create();

    $user->notify(new DependentNotification([$tag]));
    $tag->delete();

    $this->assertSame(0, $this->notificationCount());
});

test('a model with the trait is cleaned up once', function () {
    $user = User::create();
    $order = Order::create();
    $user->notify((new DependentNotification)->dependsOn($order));
    $lookups = 0;

    DB::listen(function (QueryExecuted $query) use (&$lookups) {
        $sql = strtolower($query->sql);

        if (Str::startsWith($sql, 'select') && Str::contains($sql, $this->dependencyTable())) {
            $lookups++;
        }
    });

    $order->delete();

    $this->assertSame(0, $this->notificationCount());
    $this->assertSame(1, $lookups, 'The trait cleans up; the wildcard listener skips the model.');
});

test('deleting any model costs one lookup', function () {
    $user = User::create();
    $user->notify((new DependentNotification)->dependsOn(Order::create()));
    $tag = Tag::create();
    $queries = 0;

    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $tag->delete();

    $this->assertSame(2, $queries, 'The tag delete and one dependency lookup.');
    $this->assertSame(1, $this->notificationCount());
});
