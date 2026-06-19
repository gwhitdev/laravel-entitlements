<?php

namespace Entitlements\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Resolves a user's billing plan to an opaque plan identifier and an active/inactive state.
 *
 * This is the billing seam: the default StripePlanResolver reads the user's Stripe price,
 * but a Paddle/Lemon Squeezy/no-billing implementation can be bound instead without any other
 * code change. Implementations MUST NOT assume a specific billing provider beyond their own.
 */
interface PlanResolver
{
    /**
     * The opaque identifier of the user's active plan (e.g. a Stripe price id), or null if none.
     * Stored verbatim in plan_features.plan_identifier — never assume it is Stripe-specific.
     */
    public function planIdentifier(Authenticatable $user): ?string;

    /**
     * Whether the user's plan/membership is currently active (paid, within any start/expiry window).
     */
    public function isActive(Authenticatable $user): bool;
}
