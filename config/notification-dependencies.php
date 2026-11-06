<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Dependency table
    |--------------------------------------------------------------------------
    |
    | The table that links stored database notifications to the models they
    | depend on. The package migration creates it with this name. Changing
    | it after the migration ran does not rename the existing table.
    |
    */

    'table' => 'notification_dependencies',

    /*
    |--------------------------------------------------------------------------
    | All models
    |--------------------------------------------------------------------------
    |
    | When false, a notification can only depend on models that use
    | HasDependentNotifications, and only their deletion is checked. When
    | true, any model can be a dependency without the trait, at the cost of
    | one indexed lookup on every Eloquent model deletion in the application.
    |
    */

    'all_models' => false,

    /*
    |--------------------------------------------------------------------------
    | Delete on soft delete
    |--------------------------------------------------------------------------
    |
    | When true, soft deleting a dependency deletes its database
    | notifications. When false, they are deleted only when the dependency
    | is force deleted.
    |
    */

    'delete_on_soft_delete' => true,
];
