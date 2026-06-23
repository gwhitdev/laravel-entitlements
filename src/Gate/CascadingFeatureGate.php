<?php

namespace Entitlements\Gate;

use Entitlements\Contracts\FeatureCatalog;
use Entitlements\Contracts\FeatureGate;
use Entitlements\Contracts\PlanResolver;
use Entitlements\Models\PlanFeature;
use Entitlements\Models\UserFeature;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Default resolution gate. Cascade (first match wins):
 *   1. admin override → 2. explicit per-user grant → 3. membership-active gate → 4. plan-derived.
 *
 * Each input (grants, active state, plan keys) is memoized per user per request, mirroring the
 * proven N+1-safe pattern from the source app.
 */
class CascadingFeatureGate implements FeatureGate
{
    /** @var array<int|string, list<string>> */
    private array $memoGrants = [];

    /** @var array<int|string, bool> */
    private array $memoActive = [];

    /** @var array<int|string, list<string>> */
    private array $memoPlanKeys = [];

    public function __construct(
        private PlanResolver $resolver,
        private FeatureCatalog $catalog,
    ) {}

    public function has(Authenticatable $user, string $key): bool
    {
        if ($this->isAdmin($user)) {
            return true;
        }

        if (in_array($key, $this->grants($user), true)) {
            return true;
        }

        if (! $this->active($user)) {
            return false;
        }

        return in_array($key, $this->planKeys($user), true);
    }

    public function entitlements(Authenticatable $user): array
    {
        if ($this->isAdmin($user)) {
            return array_values(array_map(fn (array $d): string => $d['key'], $this->catalog->all()));
        }

        $keys = $this->grants($user);

        if ($this->active($user)) {
            $keys = array_merge($keys, $this->planKeys($user));
        }

        return array_values(array_unique($keys));
    }

    /**
     * Flush all per-user memoization. The container binds this gate `scoped`, so Octane and
     * queue workers call this implicitly at each request/job boundary; expose it so a long-lived
     * process can also reset entitlement state explicitly after a grant/plan change.
     */
    public function flush(): void
    {
        $this->memoGrants = [];
        $this->memoActive = [];
        $this->memoPlanKeys = [];
    }

    private function isAdmin(Authenticatable $user): bool
    {
        if (! (bool) config('entitlements.admin_override')) {
            return false;
        }

        // Prefer an explicit, non-mass-assignable hook on the user model so admin status is a
        // deliberate decision. Fall back to the `is_admin` attribute only if no hook is defined —
        // consumers MUST ensure that attribute is never mass-assignable (see SECURITY notes).
        if (method_exists($user, 'isEntitlementAdmin')) {
            return (bool) $user->isEntitlementAdmin();
        }

        return (bool) ($user->is_admin ?? false);
    }

    private function id(Authenticatable $user): int|string
    {
        return $user->getAuthIdentifier();
    }

    /**
     * @return list<string>
     */
    private function grants(Authenticatable $user): array
    {
        return $this->memoGrants[$this->id($user)] ??= UserFeature::query()
            ->where('user_id', $this->id($user))
            ->pluck('feature_key')
            ->all();
    }

    private function active(Authenticatable $user): bool
    {
        return $this->memoActive[$this->id($user)] ??= $this->resolver->isActive($user);
    }

    /**
     * @return list<string>
     */
    private function planKeys(Authenticatable $user): array
    {
        return $this->memoPlanKeys[$this->id($user)] ??= $this->lookupPlanKeys($user);
    }

    /**
     * @return list<string>
     */
    private function lookupPlanKeys(Authenticatable $user): array
    {
        $plan = $this->resolver->planIdentifier($user);

        if ($plan === null) {
            return [];
        }

        if (config('entitlements.plan_store') === 'config') {
            return array_values((array) config("entitlements.plans.{$plan}", []));
        }

        return PlanFeature::query()
            ->where('plan_identifier', $plan)
            ->pluck('feature_key')
            ->all();
    }
}
