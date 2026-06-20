<?php

namespace Entitlements\Bridge;

use Entitlements\Contracts\FeatureCatalog;
use Entitlements\Contracts\FeatureGate;
use Illuminate\Contracts\Auth\Authenticatable;
use Laravel\Pennant\Feature;

/**
 * Optional bridge: registers every catalog feature as a Pennant feature, delegating
 * resolution to the entitlements gate. Enable via config('entitlements.pennant' => true).
 * Requires laravel/pennant to be installed.
 */
class PennantBridge
{
    public function __construct(
        private FeatureCatalog $catalog,
        private FeatureGate $gate,
    ) {}

    /**
     * Define each catalog feature in Pennant's registry.
     * Non-authenticatable scopes are treated as inactive.
     */
    public function register(): void
    {
        foreach ($this->catalog->all() as $definition) {
            $key = $definition['key'];

            Feature::define($key, function ($scope) use ($key): bool {
                if (! $scope instanceof Authenticatable) {
                    return false;
                }

                return $this->gate->has($scope, $key);
            });
        }
    }
}
