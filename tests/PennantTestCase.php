<?php

namespace Entitlements\Tests;

use Entitlements\EntitlementsServiceProvider;
use Laravel\Pennant\PennantServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class PennantTestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            EntitlementsServiceProvider::class,
            PennantServiceProvider::class,
        ];
    }
}
