<?php

namespace Entitlements\Models;

use BackedEnum;
use Entitlements\Support\FeatureKey;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * An explicit per-user feature grant — an override above plan-derived entitlements
 * (trials, comps, grandfathering).
 *
 * @property int|string $user_id
 * @property string $feature_key
 */
class UserFeature extends Model
{
    // Fully guarded: granting a feature is an authorization decision that must go through the
    // controlled grant() path below, never through mass assignment. This blocks a consumer from
    // accidentally letting a user grant themselves a feature via UserFeature::create($request->all()).
    protected $guarded = ['*'];

    /**
     * Grant a feature directly to a user (idempotent). Accepts a user model or id, and a
     * string key or backed enum case.
     */
    public static function grant(Authenticatable|int|string $user, string|BackedEnum $feature): self
    {
        $userId = $user instanceof Authenticatable ? $user->getAuthIdentifier() : $user;

        // unguarded() bypasses mass-assignment protection only for these two controlled,
        // non-user-arbitrary columns, then restores it.
        return static::unguarded(fn (): self => static::firstOrCreate([
            'user_id' => $userId,
            'feature_key' => FeatureKey::normalise($feature),
        ]));
    }
}
