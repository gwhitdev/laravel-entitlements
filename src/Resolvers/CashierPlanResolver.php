<?php

namespace Entitlements\Resolvers;

use Entitlements\Contracts\PlanResolver;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Default billing seam — reads the user's active subscription via Laravel Cashier.
 *
 * Deliberately uses `method_exists` rather than type-hinting Cashier's Billable, so the class
 * never hard-couples to Cashier (the billing-agnostic promise) and degrades to "no plan" if the
 * user isn't Billable. A Paddle/Lemon Squeezy resolver implements the same contract and is bound
 * in its place with no other change.
 */
class CashierPlanResolver implements PlanResolver
{
    public function planIdentifier(Authenticatable $user): ?string
    {
        if (! method_exists($user, 'subscription')) {
            return null;
        }

        return $user->subscription()?->stripe_price;
    }

    public function isActive(Authenticatable $user): bool
    {
        return method_exists($user, 'subscribed') ? (bool) $user->subscribed() : false;
    }
}
