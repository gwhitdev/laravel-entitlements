<?php

namespace Entitlements\Tests\Fixtures;

use Entitlements\Contracts\FeatureCatalog;

/**
 * Controllable FeatureCatalog for testing dependency resolution in isolation.
 */
class FakeFeatureCatalog implements FeatureCatalog
{
    /**
     * @param array<int, array{key: string, group?: string, dependencies?: array<int, string>}> $features
     */
    public function __construct(
        private array $features = [],
    ) {}

    public function all(): array
    {
        return $this->features;
    }

    public function has(string $key): bool
    {
        foreach ($this->features as $f) {
            if (($f['key'] ?? null) === $key) {
                return true;
            }
        }

        return false;
    }

    public function dependenciesFor(string $key): array
    {
        foreach ($this->features as $f) {
            if (($f['key'] ?? null) === $key) {
                return (array) ($f['dependencies'] ?? []);
            }
        }

        return [];
    }

    public function groupFor(string $key): string
    {
        foreach ($this->features as $f) {
            if (($f['key'] ?? null) === $key) {
                return $f['group'] ?? 'Other';
            }
        }

        return 'Other';
    }
}
