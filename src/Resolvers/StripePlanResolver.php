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

        $name = config('entitlements.subscription_name');

        return $name !== null
            ? $user->subscription($name)?->stripe_price
            : $user->subscription()?->stripe_price;
    }

    public function isActive(Authenticatable $user): bool
    {
        if (! method_exists($user, 'subscribed')) {
            return false;
        }

        $name = config('entitlements.subscription_name');

        return $name !== null
            ? (bool) $user->subscribed($name)
            : (bool) $user->subscribed();
    }
}
