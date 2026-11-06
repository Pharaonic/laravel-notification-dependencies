<?php

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
