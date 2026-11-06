<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Config;
use InvalidArgumentException;
use Pharaonic\Laravel\NotificationDependencies\Actions\LinkDependencies;
use Pharaonic\Laravel\NotificationDependencies\Actions\PurgeDependentNotifications;
use Pharaonic\Laravel\NotificationDependencies\Concerns\HasDependentNotifications;
use Pharaonic\Laravel\NotificationDependencies\Models\NotificationDependency;
use RuntimeException;

/**
 * The entry point of the package: identifies dependency models, and hands
 * linking and purging to their actions. Everything the actions share lives
 * here.
 *
 * A model is identified by its morph type and primary key, which is what the
 * dependency table stores. The identity also keeps the model's class and
 * connection, only to check the model still exists before linking. It is a
 * plain array, so it survives queue serialization without the model.
 *
 * @internal
 *
 * @phpstan-type Identity array{type: string, key: string, class: string, connection: string, signature: string}
 */
final class NotificationDependenciesManager
{
    public const MAX_TYPE_LENGTH = 191;

    public const MAX_KEY_LENGTH = 191;

    /**
     * The most ids put in one IN (...) list or one multi-row insert.
     */
    public const BATCH_SIZE = 500;

    /**
     * The traits of each class checked so far.
     *
     * @var array<class-string, array<mixed>>
     */
    private static $traits = [];

    /**
     * The identity of a saved model a notification depends on.
     *
     * @return Identity
     *
     * @throws InvalidArgumentException when the model does not use
     *                                  HasDependentNotifications (unless
     *                                  all_models is on), is unsaved, has no
     *                                  usable key, or does not fit the columns
     */
    public static function identify(Model $model): array
    {
        $class = get_class($model);

        if (! self::watchesAllModels() && ! self::usesTrait($model)) {
            throw new InvalidArgumentException(sprintf(
                'A notification can only depend on a model that uses %s, so its deletion deletes the notification. '
                .'Add it to [%s], or set pharaonic.notification-dependencies.all_models to true.',
                HasDependentNotifications::class,
                $class
            ));
        }

        if (! $model->exists) {
            throw new InvalidArgumentException(sprintf('A notification cannot depend on an unsaved [%s] model. Save it first.', $class));
        }

        $identity = self::resolve($model);

        if (is_string($identity)) {
            throw new InvalidArgumentException($identity);
        }

        return $identity;
    }

    /**
     * The identity of any model, or null when it could never have been
     * linked. Reads the original key, so a deleted model still matches.
     *
     * @return Identity|null
     */
    public static function identityOf(Model $model): ?array
    {
        $identity = self::resolve($model);

        return is_array($identity) ? $identity : null;
    }

    /**
     * Link a stored database notification to its dependencies.
     *
     * @param  array<string, Identity>  $identities
     */
    public static function link(Model $notification, array $identities): void
    {
        (new LinkDependencies)($notification, $identities);
    }

    /**
     * Delete every database notification that depends on a deleted model.
     */
    public static function purge(Model $model): void
    {
        (new PurgeDependentNotifications)($model);
    }

    /**
     * Delete stored notifications and their links, on the notifications'
     * connection. The links are deleted explicitly too, so nothing is left
     * behind where foreign keys are not enforced.
     *
     * @param  array<int, string>  $ids
     */
    public static function discard(Model $notification, array $ids): void
    {
        foreach (array_chunk($ids, self::BATCH_SIZE) as $chunk) {
            self::table($notification)->whereIn('notification_id', $chunk)->delete();
            $notification->getConnection()->table($notification->getTable())->whereIn($notification->getKeyName(), $chunk)->delete();
        }
    }

    /**
     * Delete one stored notification and its links.
     */
    public static function discardNotification(Model $notification): void
    {
        self::discard($notification, [self::idOf($notification)]);
    }

    /**
     * A database notification model on the connection the package works on:
     * the one DatabaseNotification resolves to by default. Links are written
     * and purged there.
     */
    public static function notificationModel(): DatabaseNotification
    {
        return new DatabaseNotification;
    }

    /**
     * Whether a stored notification lives on the package's connection.
     */
    public static function isOnNotificationConnection(Model $notification): bool
    {
        return $notification->getConnection()->getName() === self::notificationModel()->getConnection()->getName();
    }

    /**
     * A query on the dependency table, on the notifications' connection.
     */
    public static function table(Model $notification): Builder
    {
        $model = new NotificationDependency;
        $model->setConnection($notification->getConnectionName());

        return $model->getConnection()->table($model->getTable());
    }

    /**
     * Only the links of one identity.
     *
     * @param  Identity  $identity
     */
    public static function constrain(Builder $query, array $identity): Builder
    {
        return $query->where('dependable_type', $identity['type'])
            ->where('dependable_id', $identity['key']);
    }

    /**
     * The stored notification's key as a string.
     */
    public static function idOf(Model $notification): string
    {
        $id = self::keyOf($notification->getKey());

        if ($id === null) {
            throw new RuntimeException('The stored notification has no usable primary key.');
        }

        return $id;
    }

    /**
     * Whether every deleted model is checked, with or without the trait.
     */
    public static function watchesAllModels(): bool
    {
        return (bool) Config::get('pharaonic.notification-dependencies.all_models', false);
    }

    /**
     * Whether the model deletes its notifications through its own trait.
     */
    public static function usesTrait(Model $model): bool
    {
        return self::hasTrait($model, HasDependentNotifications::class);
    }

    /**
     * Whether the object's class uses the trait, cached per class: this runs
     * on every deletion when all_models is on, and on every notification sent.
     */
    public static function hasTrait(object $object, string $trait): bool
    {
        $class = get_class($object);

        if (! isset(self::$traits[$class])) {
            self::$traits[$class] = class_uses_recursive($object);
        }

        return in_array($trait, self::$traits[$class], true);
    }

    public static function deletesOnSoftDelete(): bool
    {
        return (bool) Config::get('pharaonic.notification-dependencies.delete_on_soft_delete', true);
    }

    public static function isSoftDelete(Model $model): bool
    {
        return method_exists($model, 'isForceDeleting') && ! $model->isForceDeleting();
    }

    /**
     * The identity of a model, or why it cannot have one. The one place the
     * identity rules live, so linking and purging can never disagree.
     *
     * @return Identity|string
     */
    private static function resolve(Model $model)
    {
        $class = get_class($model);
        $key = self::keyOf($model->getOriginal($model->getKeyName(), $model->getKey()));

        if ($key === null) {
            return sprintf('A notification can only depend on a [%s] model with an integer or non-empty string primary key.', $class);
        }

        $type = $model->getMorphClass();

        foreach ([
            'morph type' => [$type, self::MAX_TYPE_LENGTH],
            'primary key' => [$key, self::MAX_KEY_LENGTH],
        ] as $what => [$value, $limit]) {
            if (strlen($value) > $limit) {
                return sprintf('The %s of the [%s] dependency is longer than %d characters.', $what, $class, $limit);
            }
        }

        return self::makeIdentity((string) $model->getConnection()->getName(), $type, $class, $key);
    }

    /**
     * The signature removes duplicates in memory and is never stored.
     * serialize() prefixes every string with its length, so two different
     * (type, key) pairs never share one.
     *
     * @return Identity
     */
    private static function makeIdentity(string $connection, string $type, string $class, string $key): array
    {
        return [
            'type' => $type,
            'key' => $key,
            'class' => $class,
            'connection' => $connection,
            'signature' => serialize([$type, $key]),
        ];
    }

    /**
     * @param  mixed  $key
     */
    private static function keyOf($key): ?string
    {
        if (is_int($key)) {
            return (string) $key;
        }

        return is_string($key) && $key !== '' ? $key : null;
    }
}
