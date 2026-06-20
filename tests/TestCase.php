<?php

namespace Elyon\LaravelStandards\Tests;

use Elyon\LaravelStandards\StandardsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            StandardsServiceProvider::class,
        ];
    }
}
