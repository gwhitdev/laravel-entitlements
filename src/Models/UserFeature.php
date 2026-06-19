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
    protected $fillable = ['user_id', 'feature_key'];

    /**
     * Grant a feature directly to a user (idempotent). Accepts a user model or id, and a
     * string key or backed enum case.
     */
    public static function grant(Authenticatable|int|string $user, string|BackedEnum $feature): self
    {
        $userId = $user instanceof Authenticatable ? $user->getAuthIdentifier() : $user;

        return static::firstOrCreate([
            'user_id' => $userId,
            'feature_key' => FeatureKey::normalise($feature),
        ]);
    }
}
