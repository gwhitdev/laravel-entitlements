<?php

namespace Entitlements\Facades;

use Entitlements\Contracts\FeatureGate;
use Illuminate\Support\Facades\Facade;

/**
 * Tessera — the developer-facing brand for entitlement checks.
 *
 * @method static bool has(\Illuminate\Contracts\Auth\Authenticatable $user, string $key)
 * @method static array entitlements(\Illuminate\Contracts\Auth\Authenticatable $user)
 *
 * @see FeatureGate
 */
class Tessera extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return FeatureGate::class;
    }
}
