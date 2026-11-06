<?php

use Illuminate\Support\Facades\DB;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Comment;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\DependentNotification;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\OrderCommented;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Notifications\OrderUpdated;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Order;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\Product;
use Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures\User;

test('depends on returns the same notification', function () {
    $notification = new DependentNotification;

    $this->assertSame($notification, $notification->dependsOn(Order::create()));
});

test('depends on one model', function () {
    $order = Order::create();

    User::create()->notify((new DependentNotification)->dependsOn($order));

    $this->assertSame([[Order::class, (string) $order->getKey()]], dependsOnLinks());
});

test('depends on several models in one call', function () {
    $order = Order::create();
    $comment = Comment::create();

    User::create()->notify((new DependentNotification)->dependsOn($order, $comment));

    $this->assertSame([
        [Order::class, (string) $order->getKey()],
        [Comment::class, (string) $comment->getKey()],
    ], dependsOnLinks());
});

test('chained calls add to the set', function () {
    $order = Order::create();
    $comment = Comment::create();
    $product = Product::create();

    $notification = (new DependentNotification)
        ->dependsOn($order)
        ->dependsOn($comment, $product);

    User::create()->notify($notification);

    $this->assertSame([Order::class, Comment::class, Product::class], array_column(dependsOnLinks(), 0));
});

test('duplicates are ignored within and across calls', function () {
    $order = Order::create();
    $comment = Comment::create();

    $notification = (new DependentNotification)
        ->dependsOn($order, $order, Order::find($order->getKey()))
        ->dependsOn($comment, $order);

    User::create()->notify($notification);

    $this->assertSame([Order::class, Comment::class], array_column(dependsOnLinks(), 0));
});

test('declared and added dependencies merge without duplicates', function () {
    $order = Order::create();
    $comment = Comment::create();

    User::create()->notify((new DependentNotification([$order]))->dependsOn($comment, $order));

    $this->assertSame([
        [Order::class, (string) $order->getKey()],
        [Comment::class, (string) $comment->getKey()],
    ], dependsOnLinks());
});

test('depends on without models does nothing', function () {
    $notification = new DependentNotification;

    $this->assertSame($notification, $notification->dependsOn());

    User::create()->notify($notification);

    $this->assertSame(1, $this->notificationCount());
    $this->assertSame(0, $this->dependencyCount());
});

test('dependencies defaults to an empty array', function () {
    $this->assertSame([], (new OrderUpdated(Order::create()))->dependencies());
});

test('dependencies declared in the class', function () {
    $order = Order::create();
    $comment = Comment::create();

    User::create()->notify(new OrderCommented($order, $comment));

    $this->assertSame([Order::class, Comment::class], array_column(dependsOnLinks(), 0));
});

test('depends on inside the constructor', function () {
    $order = Order::create();
    $comment = Comment::create();
    $product = Product::create();

    User::create()->notify(new OrderUpdated($order, [$comment, $product]));

    $this->assertSame([Order::class, Comment::class, Product::class], array_column(dependsOnLinks(), 0));
});

test('an unsaved model is rejected', function () {
    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('cannot depend on an unsaved ['.Order::class.'] model');

    (new DependentNotification)->dependsOn(new Order);
});

test('a deleted model is rejected', function () {
    $order = Order::create();
    $order->delete();

    $this->expectException(InvalidArgumentException::class);

    (new DependentNotification)->dependsOn($order);
});

test('a model without a key is rejected', function () {
    $order = new Order;
    $order->exists = true;

    $this->expectException(InvalidArgumentException::class);
    $this->expectExceptionMessage('integer or non-empty string primary key');

    (new DependentNotification)->dependsOn($order);
});

test('a rejected model leaves the set unchanged', function () {
    $order = Order::create();
    $notification = (new DependentNotification)->dependsOn($order);

    try {
        $notification->dependsOn(new Comment);
        $this->fail('An unsaved model was accepted.');
    } catch (InvalidArgumentException $e) {
        //
    }

    User::create()->notify($notification);

    $this->assertSame([Order::class], array_column(dependsOnLinks(), 0));
});

/**
 * @return array<int, array{0: string, 1: string}>
 */
function dependsOnLinks(): array
{
    return DB::table(dependencyTable())
        ->orderBy('id')
        ->get(['dependable_type', 'dependable_id'])
        ->map(static function ($row): array {
            return [(string) $row->dependable_type, (string) $row->dependable_id];
        })
        ->all();
}
