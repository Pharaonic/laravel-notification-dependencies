<?php

use Illuminate\Database\Eloquent\Relations\Relation;
use Pharaonic\Laravel\NotificationDependencies\Models\NotificationDependency;
use Pharaonic\Laravel\NotificationDependencies\Support\NotificationDependenciesManager;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Comment;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Order;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\UuidModel;

test('it captures the normalized identity', function () {
    $order = Order::create();
    $identity = NotificationDependenciesManager::identify($order);

    $this->assertSame('testing', $identity['connection']);
    $this->assertSame(Order::class, $identity['type']);
    $this->assertSame(Order::class, $identity['class']);
    $this->assertSame((string) $order->getKey(), $identity['key']);
    $this->assertSame(serialize([Order::class, (string) $order->getKey()]), $identity['signature']);
});

test('a deleted model still matches its links', function () {
    $order = Order::create();
    $identity = NotificationDependenciesManager::identify($order);

    $order->delete();

    $this->assertSame(
        NotificationDependency::query()->whereDependable($order)->toSql(),
        NotificationDependency::query()
            ->where('dependable_type', $identity['type'])
            ->where('dependable_id', $identity['key'])
            ->toSql()
    );
    $this->assertSame(
        [$identity['type'], $identity['key']],
        NotificationDependency::query()->whereDependable($order)->getBindings()
    );
});

test('the signature has no separator collisions', function () {
    Relation::morphMap(['ab' => UuidModel::class]);
    $model = new UuidModel(['id' => 'c']);
    $model->exists = true;

    $signature = NotificationDependenciesManager::identify($model)['signature'];

    $this->assertSame(serialize(['ab', 'c']), $signature);
    $this->assertNotSame(serialize(['a', 'bc']), $signature, 'Concatenation would make these equal.');
});

test('the signature uses the morph alias', function () {
    $order = Order::create();
    $byClass = NotificationDependenciesManager::identify($order)['signature'];

    Relation::morphMap(['order' => Order::class]);

    $this->assertSame('order', NotificationDependenciesManager::identify($order)['type']);
    $this->assertNotSame($byClass, NotificationDependenciesManager::identify($order)['signature']);
});

test('different types with the same key differ', function () {
    $this->assertNotSame(
        NotificationDependenciesManager::identify(Order::create())['signature'],
        NotificationDependenciesManager::identify(Comment::create())['signature']
    );
});

test('it survives serialization', function () {
    $identity = NotificationDependenciesManager::identify(UuidModel::create());

    $this->assertSame($identity, unserialize(serialize($identity)));
});

test('a key longer than the column is rejected', function () {
    $model = new UuidModel(['id' => str_repeat('x', NotificationDependenciesManager::MAX_KEY_LENGTH + 1)]);
    $model->exists = true;

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('primary key of the ['.UuidModel::class.'] dependency is longer than 191 characters');

    NotificationDependenciesManager::identify($model);
});

test('a morph type longer than the column is rejected', function () {
    Relation::morphMap([str_repeat('t', NotificationDependenciesManager::MAX_TYPE_LENGTH + 1) => Order::class]);

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('morph type');

    NotificationDependenciesManager::identify(Order::create());
});

test('an empty string key is rejected', function () {
    $model = new UuidModel(['id' => '']);
    $model->exists = true;

    $this->expectException(InvalidArgumentException::class);

    NotificationDependenciesManager::identify($model);
});

test('a model that could never be linked matches nothing', function () {
    $this->assertStringContainsString('1 = 0', NotificationDependency::query()->whereDependable(new Order)->toSql());
    $this->assertStringContainsString('1 = 0', NotificationDependency::query()->whereDependable(new UuidModel(['id' => str_repeat('x', 192)]))->toSql());
});
