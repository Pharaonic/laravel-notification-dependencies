<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Schema;
use Pharaonic\Laravel\NotificationDependencies\Models\NotificationDependency;

/**
 * One row per stored database notification and model it depends on.
 * Deleting the notification deletes its rows through the foreign key.
 */
class CreatePharaonicNotificationDependenciesTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $name = (new NotificationDependency)->getTable();
        $notifications = (new DatabaseNotification)->getTable();

        if (! Schema::hasTable($notifications)) {
            throw new RuntimeException(sprintf(
                'pharaonic/laravel-notification-dependencies needs the [%s] table first. '
                .'Run "php artisan notifications:table" and "php artisan migrate", then migrate again.',
                $notifications
            ));
        }

        if (Schema::hasTable($name)) {
            throw new RuntimeException(sprintf(
                'The [%s] table already exists, so pharaonic/laravel-notification-dependencies cannot create it. '
                .'Set pharaonic.notification-dependencies.table to another name, or move the existing table out of the way.',
                $name
            ));
        }

        Schema::create($name, function (Blueprint $table) use ($name, $notifications) {
            $table->bigIncrements('id');
            $table->uuid('notification_id');
            $table->string('dependable_type', 191);
            $table->string('dependable_id', 191);
            $table->timestamps();

            // Leads with notification_id, so it also serves the foreign key.
            $table->unique(['notification_id', 'dependable_type', 'dependable_id'], $name.'_notification_dependable_unique');
            // Finds the links of a deleted model.
            $table->index(['dependable_type', 'dependable_id'], $name.'_dependable_index');

            $table->foreign('notification_id', $name.'_notification_id_foreign')
                ->references('id')
                ->on($notifications)
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists((new NotificationDependency)->getTable());
    }
}
