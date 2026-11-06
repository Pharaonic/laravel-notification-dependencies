<?php

declare(strict_types=1);

namespace Pharaonic\Laravel\NotificationDependencies\Tests\Fixtures;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema as SchemaFacade;

/**
 * Laravel 6's notifications and jobs tables, as their stubs create them,
 * and the fixture tables. The dependency table comes from the package migration.
 */
final class Schema
{
    public static function create(): void
    {
        self::notifications();

        foreach (['users', 'orders', 'comments', 'products', 'tags'] as $name) {
            SchemaFacade::create($name, function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('name')->nullable();
                $table->timestamps();
            });
        }

        SchemaFacade::create('soft_orders', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        SchemaFacade::create('uuid_models', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        SchemaFacade::create('ulid_models', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('name')->nullable();
            $table->timestamps();
        });

        SchemaFacade::create('jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('queue')->index();
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts');
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });

        SchemaFacade::create('failed_jobs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at')->useCurrent();
        });
    }

    public static function notifications(): void
    {
        SchemaFacade::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
    }
}
