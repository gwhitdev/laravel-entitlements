<?php

namespace Entitlements\Contracts;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * The public resolution API — the only surface the bundled UI and consumers touch (keeps the
 * package headless by construction). Fronted by the Tessera facade and the HasFeatures trait.
 *
 * Resolution cascade (see SPEC.md): admin override → explicit per-user grant → membership
 * active gate → plan-derived grant. Implementations memoize per request.
 */
interface FeatureGate
{
    /**
     * Whether the user is entitled to the given feature key.
     */
    public function has(Authenticatable $user, string $key): bool;

    /**
     * All feature keys the user is currently entitled to.
     *
     * @return array<int, string>
     */
    public function entitlements(Authenticatable $user): array;
}
