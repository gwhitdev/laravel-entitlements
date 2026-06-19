<?php

namespace Entitlements\Resolvers;

use Entitlements\Contracts\PlanResolver;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Default billing seam — resolves the user's Stripe plan, implemented via Laravel Cashier.
 *
 * Named for the billing PROVIDER (Stripe), not the package (Cashier), so it sits in a consistent
 * series with future PaddlePlanResolver / LemonSqueezyPlanResolver. Deliberately uses
 * `method_exists` rather than type-hinting Cashier's Billable, so the class never hard-couples to
 * Cashier (the billing-agnostic promise) and degrades to "no plan" if the user isn't Billable.
 */
class StripePlanResolver implements PlanResolver
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
