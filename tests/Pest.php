<?php

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Queue\Console\WorkCommand;
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
    // queue:work registers its failed-job logger once per process; older
    // Testbench releases do not reset that between tests, so the logger
    // would keep writing to the first test's application.
    WorkCommand::flushState();

    app(Kernel::class)->call('queue:work', [
        'connection' => 'database',
        '--once' => true,
        '--tries' => 1,
        '--sleep' => 0,
    ]);
}
