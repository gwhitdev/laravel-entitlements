<?php

namespace Entitlements\Bridge;

use Entitlements\Contracts\FeatureCatalog;
use Entitlements\Contracts\FeatureGate;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Pennant\Feature;

/**
 * Optional bridge that registers every entitlement as a Pennant feature, so teams already using
 * Pennant's check API can adopt entitlements without changing call sites.
 *
 * Call PennantBridge::register() in AppServiceProvider::boot() to opt in. Pennant's own storage
 * and caching are bypassed — resolution always goes through the entitlement cascade.
 */
class PennantBridge
{
    public static function register(): void
    {
        $catalog = app(FeatureCatalog::class);
        $gate = app(FeatureGate::class);

        foreach ($catalog->all() as $definition) {
            $key = $definition['key'];

            Feature::define($key, static function ($scope) use ($gate, $key): bool {
                return $scope instanceof Authenticatable && $gate->has($scope, $key);
            });
        }
    }
}
