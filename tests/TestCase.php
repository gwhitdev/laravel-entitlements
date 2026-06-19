<?php

namespace Entitlements\Tests;

use Entitlements\EntitlementsServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    /**
     * @return array<int, class-string>
     */
    protected function getPackageProviders($app): array
    {
        return [
            EntitlementsServiceProvider::class,
        ];
    }
}
