<?php

namespace Entitlements\Support;

use Entitlements\Contracts\FeatureCatalog;
use Entitlements\Models\PlanFeature;

/**
 * Read-only analysis of declared prerequisite satisfaction across plans.
 *
 * Never changes entitlements; surfaces unmet prerequisites for soft admin guidance and lint.
 */
class PrerequisiteChecker
{
    public function __construct(private FeatureCatalog $catalog) {}

    /**
     * Returns features mapped to the given plan that are missing one or more declared prerequisites.
     *
     * Only features with at least one missing prerequisite are included. Features whose prerequisites
     * are all also mapped to the plan are omitted (they are satisfied).
     *
     * @return array<string, array<int, string>>  ['feature_key' => ['missing_prereq_key', ...], ...]
     */
    public function forPlan(string $planIdentifier): array
    {
        $mapped = $this->mappedKeysForPlan($planIdentifier);
        $mappedSet = array_flip($mapped);

        $issues = [];

        foreach ($mapped as $featureKey) {
            $deps = $this->catalog->dependenciesFor($featureKey);
            $missing = array_values(array_filter($deps, fn (string $dep): bool => ! isset($mappedSet[$dep])));

            if (! empty($missing)) {
                $issues[$featureKey] = $missing;
            }
        }

        return $issues;
    }

    /**
     * Returns prerequisite issues across every known plan, omitting plans with no issues.
     *
     * @return array<string, array<string, array<int, string>>>  ['plan_identifier' => forPlan result, ...]
     */
    public function all(): array
    {
        $result = [];

        foreach ($this->allPlanIdentifiers() as $planIdentifier) {
            $issues = $this->forPlan($planIdentifier);

            if (! empty($issues)) {
                $result[$planIdentifier] = $issues;
            }
        }

        return $result;
    }

    /**
     * Resolve the feature keys mapped to a plan, honouring the configured plan_store.
     *
     * @return array<int, string>
     */
    private function mappedKeysForPlan(string $planIdentifier): array
    {
        if (config('entitlements.plan_store') === 'config') {
            return config("entitlements.plans.{$planIdentifier}", []);
        }

        return PlanFeature::where('plan_identifier', $planIdentifier)
            ->pluck('feature_key')
            ->all();
    }

    /**
     * Enumerate all known plan identifiers, honouring the configured plan_store.
     *
     * @return array<int, string>
     */
    private function allPlanIdentifiers(): array
    {
        if (config('entitlements.plan_store') === 'config') {
            return array_keys(config('entitlements.plans', []));
        }

        return PlanFeature::distinct()
            ->pluck('plan_identifier')
            ->all();
    }
}
