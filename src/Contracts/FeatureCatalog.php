<?php

namespace Entitlements\Contracts;

/**
 * The source of truth for the set of features the application defines.
 *
 * This is the catalog seam. The default driver is enum-backed (EnumFeatureCatalog); config-array
 * and database drivers can be bound instead. Catalog changes are coupled to code (a new key is
 * inert until something checks it), so the default lives in code — but the contract is identical
 * across drivers.
 */
interface FeatureCatalog
{
    /**
     * Every defined feature.
     *
     * @return array<int, array{key: string, name: string, description: ?string, group: string, dependencies: array<int, string>}>
     */
    public function all(): array;

    /**
     * Whether a feature key is defined in the catalog.
     */
    public function has(string $key): bool;

    /**
     * Direct prerequisite feature keys for the given key.
     *
     * @return array<int, string>
     */
    public function dependenciesFor(string $key): array;

    /**
     * The display group for a key. Unknown keys fall into "Other".
     */
    public function groupFor(string $key): string;
}
