<?php

namespace Entitlements\Catalog;

use Entitlements\Contracts\FeatureCatalog;

/**
 * Config-driven catalog driver — reads feature definitions from config('entitlements.features').
 * Each entry must have 'key' and may have 'group' (defaults to "Other") and 'dependencies'.
 */
class ConfigFeatureCatalog implements FeatureCatalog
{
    public function all(): array
    {
        return config('entitlements.features', []);
    }

    public function has(string $key): bool
    {
        foreach ($this->all() as $definition) {
            if (($definition['key'] ?? null) === $key) {
                return true;
            }
        }

        return false;
    }

    public function dependenciesFor(string $key): array
    {
        foreach ($this->all() as $definition) {
            if (($definition['key'] ?? null) === $key) {
                return (array) ($definition['dependencies'] ?? []);
            }
        }

        return [];
    }

    public function groupFor(string $key): string
    {
        foreach ($this->all() as $definition) {
            if (($definition['key'] ?? null) === $key) {
                return $definition['group'] ?? 'Other';
            }
        }

        return 'Other';
    }
}
