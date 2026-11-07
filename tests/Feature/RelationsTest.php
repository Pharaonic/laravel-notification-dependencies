<?php

use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\DB;
use Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependentNotifications;
use Pharaonic\Laravel\NotificationDependencies\Models\NotificationDependency;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Comment;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\DependentNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\PlainNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Order;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\User;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\UuidModel;

test('a model without the trait cannot be a dependency', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('uses '.HasDependentNotifications::class);

    (new DependentNotification)->dependsOn(User::create());
});

test('a declared dependency without the trait removes the stored notification', function () {
    $user = User::create();

    try {
        $user->notify(new DependentNotification([User::create()]));
        $this->fail('A model without the trait was accepted.');
    } catch (InvalidArgumentException $e) {
        $this->assertStringContainsString(HasDependentNotifications::class, $e->getMessage());
    }

    $this->assertSame(0, $this->notificationCount());
});

test('dependent notifications returns the stored rows of every recipient', function () {
    $alice = User::create();
    $bob = User::create();
    $order = Order::create();
    $other = Order::create();

    $alice->notify((new DependentNotification)->dependsOn($order));
    $bob->notify((new DependentNotification)->dependsOn($order, Comment::create()));
    $alice->notify((new DependentNotification)->dependsOn($other));
    $alice->notify(new PlainNotification);

    $notifications = $order->dependentNotifications()->get();

    $this->assertCount(2, $notifications);
    $this->assertContainsOnlyInstancesOf(DatabaseNotification::class, $notifications);
    $this->assertEqualsCanonicalizing(
        [$alice->getKey(), $bob->getKey()],
        $notifications->pluck('notifiable_id')->map(function ($id) {
            return (int) $id;
        })->all()
    );
    $this->assertCount(1, $other->dependentNotifications);
});

test('dependent notifications can be eager loaded', function () {
    $user = User::create();
    [$first, $second] = [Order::create(), Order::create()];
    $user->notify((new DependentNotification)->dependsOn($first, $second));
    $user->notify((new DependentNotification)->dependsOn($second));

    $orders = Order::query()->with('dependentNotifications')->orderBy('id')->get();

    $this->assertSame([1, 2], $orders->map(function (Order $order) {
        return $order->dependentNotifications->count();
    })->all());
});

test('has where has and with count', function () {
    $user = User::create();
    [$first, $second, $none] = [Order::create(), Order::create(), Order::create()];
    $user->notify((new DependentNotification)->dependsOn($first, $second));
    $user->notify((new DependentNotification)->dependsOn($second));

    $this->assertSame([$first->getKey(), $second->getKey()], Order::query()->has('dependentNotifications')->orderBy('id')->pluck('id')->all());
    $this->assertSame([$second->getKey()], Order::query()->has('dependentNotifications', '>', 1)->pluck('id')->all());
    $this->assertSame([$none->getKey()], Order::query()->doesntHave('dependentNotifications')->pluck('id')->all());
    $onlySecond = $second->dependentNotifications()->get()->first(function ($notification) use ($first) {
        return ! $first->dependentNotifications->contains($notification);
    });
    $this->assertNotNull($onlySecond);
    $this->assertSame([$second->getKey()], Order::query()->whereHas('dependentNotifications', function ($query) use ($onlySecond) {
        $query->where('notifications.id', $onlySecond->getKey());
    })->pluck('id')->all());
    $this->assertSame([1, 2, 0], Order::query()->withCount('dependentNotifications')->orderBy('id')->get()->map(function (Order $order) {
        return (int) $order->getAttribute('dependent_notifications_count');
    })->all());
});

test('where has with uuid keys', function () {
    $user = User::create();
    [$with, $without] = [UuidModel::create(), UuidModel::create()];
    $user->notify((new DependentNotification)->dependsOn($with));

    $this->assertSame([$with->getKey()], UuidModel::query()->whereHas('dependentNotifications')->pluck('id')->all());
    $this->assertNotSame($with->getKey(), $without->getKey());
});

test('dependent notifications with string keys', function () {
    $user = User::create();
    $model = UuidModel::create();
    $user->notify((new DependentNotification)->dependsOn($model));

    $this->assertCount(1, $model->dependentNotifications);
});

test('the dependency model reaches the notification and the model', function () {
    $user = User::create();
    $order = Order::create();
    $user->notify((new DependentNotification)->dependsOn($order));

    $link = NotificationDependency::query()->firstOrFail();

    $this->assertSame('notification_dependencies', $link->getTable());
    $this->assertInstanceOf(DatabaseNotification::class, $link->notification);
    $this->assertSame($user->notifications()->value('id'), $link->notification->getKey());
    $this->assertInstanceOf(Order::class, $link->dependable);
    $this->assertTrue($link->dependable->is($order));
});

test('the where dependable scope returns the links of one model', function () {
    $user = User::create();
    $order = Order::create();
    $comment = Comment::create();
    $user->notify((new DependentNotification)->dependsOn($order, $comment));
    $user->notify((new DependentNotification)->dependsOn($order));

    $links = NotificationDependency::whereDependable($order)->with('notification')->get();

    $this->assertCount(2, $links);
    $this->assertSame([Order::class, Order::class], $links->pluck('dependable_type')->all());
    $this->assertContainsOnlyInstancesOf(DatabaseNotification::class, $links->pluck('notification'));
    $this->assertCount(1, NotificationDependency::whereDependable($comment)->get());
    $this->assertCount(0, NotificationDependency::whereDependable(new Order)->get());
});

test('the dependency model fills only its link columns', function () {
    $model = new NotificationDependency;

    $this->assertSame(['notification_id', 'dependable_type', 'dependable_id'], $model->getFillable());
    $this->assertFalse($model->isFillable('id'));
});

test('the existence query compares the key as a string on mysql and mariadb', function (string $driver) {
    // Only compiled, never run: no database server is needed.
    config(["database.connections.{$driver}_compiled" => [
        'driver' => $driver,
        'host' => '127.0.0.1',
        'database' => 'compiled',
        'username' => 'compiled',
        'password' => '',
        'prefix' => '',
    ]]);
    DB::setDefaultConnection("{$driver}_compiled");

    $sql = Order::query()->whereHas('dependentNotifications')->toSql();

    $this->assertStringContainsString('concat(`orders`.`id`) = `'.dependencyTable().'`.`dependable_id`', $sql);
})->with(['mysql', 'mariadb']);
