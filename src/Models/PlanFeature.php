<?php

namespace Entitlements\Models;

use BackedEnum;
use Entitlements\Support\FeatureKey;
use Illuminate\Database\Eloquent\Model;

/**
 * Maps an opaque plan identifier (e.g. a Stripe price id) to a feature key.
 *
 * @property string $plan_identifier
 * @property string $feature_key
 */
class PlanFeature extends Model
{
    // Fully guarded: plan → feature mappings define what each plan unlocks, so they must only be
    // written through the controlled grant() path, never via mass assignment from request input.
    protected $guarded = ['*'];

    /**
     * Bind a feature to a plan (idempotent). Accepts a string key or a backed enum case.
     */
    public static function grant(string $planIdentifier, string|BackedEnum $feature): self
    {
        return static::unguarded(fn (): self => static::firstOrCreate([
            'plan_identifier' => $planIdentifier,
            'feature_key' => FeatureKey::normalise($feature),
        ]));
    }
}
