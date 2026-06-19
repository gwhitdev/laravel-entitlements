<?php

namespace Entitlements\Tests\Fixtures;

use Entitlements\Contracts\PlanResolver;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Controllable PlanResolver for isolating the cascade from any real billing provider, and for
 * asserting memoization (isActiveCalls counts how often the gate hit the resolver).
 */
class FakePlanResolver implements PlanResolver
{
    public int $isActiveCalls = 0;

    public function __construct(
        public ?string $plan = null,
        public bool $active = false,
    ) {}

    public function planIdentifier(Authenticatable $user): ?string
    {
        return $this->plan;
    }

    public function isActive(Authenticatable $user): bool
    {
        $this->isActiveCalls++;

        return $this->active;
    }
}
