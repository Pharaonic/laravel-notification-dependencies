<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Unit;

use Illuminate\Database\Eloquent\Relations\Relation;
use InvalidArgumentException;
use Pharaonic\Laravel\NotificationDependencies\Models\NotificationDependency;
use Pharaonic\Laravel\NotificationDependencies\Support\NotificationDependenciesManager;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Comment;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Order;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\UuidModel;
use Pharaonic\Laravel\NotificationDependencies\Tests\TestCase;

class IdentityTest extends TestCase
{
    public function test_it_captures_the_normalized_identity(): void
    {
        $order = Order::create();
        $identity = NotificationDependenciesManager::identify($order);

        $this->assertSame('testing', $identity['connection']);
        $this->assertSame(Order::class, $identity['type']);
        $this->assertSame(Order::class, $identity['class']);
        $this->assertSame((string) $order->getKey(), $identity['key']);
        $this->assertSame(serialize([Order::class, (string) $order->getKey()]), $identity['signature']);
    }

    public function test_a_deleted_model_still_matches_its_links(): void
    {
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
    }

    public function test_the_signature_has_no_separator_collisions(): void
    {
        Relation::morphMap(['ab' => UuidModel::class]);
        $model = new UuidModel(['id' => 'c']);
        $model->exists = true;

        $signature = NotificationDependenciesManager::identify($model)['signature'];

        $this->assertSame(serialize(['ab', 'c']), $signature);
        $this->assertNotSame(serialize(['a', 'bc']), $signature, 'Concatenation would make these equal.');
    }

    public function test_the_signature_uses_the_morph_alias(): void
    {
        $order = Order::create();
        $byClass = NotificationDependenciesManager::identify($order)['signature'];

        Relation::morphMap(['order' => Order::class]);

        $this->assertSame('order', NotificationDependenciesManager::identify($order)['type']);
        $this->assertNotSame($byClass, NotificationDependenciesManager::identify($order)['signature']);
    }

    public function test_different_types_with_the_same_key_differ(): void
    {
        $this->assertNotSame(
            NotificationDependenciesManager::identify(Order::create())['signature'],
            NotificationDependenciesManager::identify(Comment::create())['signature']
        );
    }

    public function test_it_survives_serialization(): void
    {
        $identity = NotificationDependenciesManager::identify(UuidModel::create());

        $this->assertSame($identity, unserialize(serialize($identity)));
    }

    public function test_a_key_longer_than_the_column_is_rejected(): void
    {
        $model = new UuidModel(['id' => str_repeat('x', NotificationDependenciesManager::MAX_KEY_LENGTH + 1)]);
        $model->exists = true;

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('primary key of the ['.UuidModel::class.'] dependency is longer than 191 characters');

        NotificationDependenciesManager::identify($model);
    }

    public function test_a_morph_type_longer_than_the_column_is_rejected(): void
    {
        Relation::morphMap([str_repeat('t', NotificationDependenciesManager::MAX_TYPE_LENGTH + 1) => Order::class]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('morph type');

        NotificationDependenciesManager::identify(Order::create());
    }

    public function test_an_empty_string_key_is_rejected(): void
    {
        $model = new UuidModel(['id' => '']);
        $model->exists = true;

        $this->expectException(InvalidArgumentException::class);

        NotificationDependenciesManager::identify($model);
    }

    public function test_a_model_that_could_never_be_linked_matches_nothing(): void
    {
        $this->assertStringContainsString('1 = 0', NotificationDependency::query()->whereDependable(new Order)->toSql());
        $this->assertStringContainsString('1 = 0', NotificationDependency::query()->whereDependable(new UuidModel(['id' => str_repeat('x', 192)]))->toSql());
    }
}
