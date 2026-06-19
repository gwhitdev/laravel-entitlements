<?php

namespace Entitlements\Concerns;

use BackedEnum;
use Entitlements\Contracts\FeatureGate;
use Entitlements\Models\UserFeature;
use Entitlements\Support\FeatureKey;

/**
 * Add to the User model for ergonomic entitlement checks. Delegates to the bound FeatureGate
 * (the public surface), so the cascade/memoization live in one place.
 */
trait HasFeatures
{
    public function hasFeature(string|BackedEnum $key): bool
    {
        return app(FeatureGate::class)->has($this, FeatureKey::normalise($key));
    }

    /**
     * All feature keys this user is currently entitled to.
     *
     * @return array<int, string>
     */
    public function features(): array
    {
        return app(FeatureGate::class)->entitlements($this);
    }

    /**
     * Grant a feature directly to this user (an override above their plan).
     */
    public function grantFeature(string|BackedEnum $feature): void
    {
        UserFeature::grant($this, $feature);
    }
}
