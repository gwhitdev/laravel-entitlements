<?php

namespace Entitlements;

use Entitlements\Contracts\FeatureGate;
use Illuminate\Contracts\Auth\Authenticatable;

class Entitlements
{
    /**
     * Return the array of feature keys the given user is entitled to.
     *
     * @return array<int, string>
     */
    public static function forUser(Authenticatable $user): array
    {
        return app(FeatureGate::class)->entitlements($user);
    }
}
