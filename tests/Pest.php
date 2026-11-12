<?php

use Illuminate\Contracts\Console\Kernel;
use Pharaonic\Laravel\NotificationDependencies\Models\NotificationDependency;
use Pharaonic\Laravel\NotificationDependencies\Tests\TestCase;

uses(TestCase::class)->in('Feature', 'Unit');

/**
 * The dependency table, as the package resolves it from the configuration.
 */
function dependencyTable(): string
{
    return (new NotificationDependency)->getTable();
}

/**
 * Run one job from the database queue, as a worker does.
 */
function workQueue(): void
{
    app(Kernel::class)->call('queue:work', [
        'connection' => 'database',
        '--once' => true,
        '--tries' => 1,
        '--sleep' => 0,
    ]);
}
