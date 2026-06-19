<?php

namespace Entitlements\Catalog;

use Entitlements\Contracts\FeatureCatalog;
use Illuminate\Support\Str;

/**
 * Config-driven catalog driver reading `config('entitlements.features')`.
 *
 * Each entry is normalised to the full contract shape:
 * {key, name, description, group, dependencies}.
 *
 * Defaults applied when keys are absent:
 *   name        = Str::headline($key)
 *   description = null
 *   group       = 'Other'
 *   dependencies = []
 */
class ConfigFeatureCatalog implements FeatureCatalog
{
    /**
     * Every defined feature, fully normalised.
     *
     * @return array<int, array{key: string, name: string, description: ?string, group: string, dependencies: array<int, string>}>
     */
    public function all(): array
    {
        $entries = config('entitlements.features', []);

        if (! is_array($entries)) {
            return [];
        }

        return array_values(array_map(function (array $entry): array {
            $key = (string) ($entry['key'] ?? '');

            return [
                'key' => $key,
                'name' => $entry['name'] ?? Str::headline($key),
                'description' => $entry['description'] ?? null,
                'group' => $entry['group'] ?? 'Other',
                'dependencies' => $entry['dependencies'] ?? [],
            ];
        }, $entries));
    }

    /**
     * Whether a feature key is defined in the catalog.
     */
    public function has(string $key): bool
    {
        foreach ($this->all() as $definition) {
            if ($definition['key'] === $key) {
                return true;
            }
        }

        return false;
    }

    /**
     * Direct prerequisite feature keys for the given key.
     *
     * @return array<int, string>
     */
    public function dependenciesFor(string $key): array
    {
        foreach ($this->all() as $definition) {
            if ($definition['key'] === $key) {
                return $definition['dependencies'];
            }
        }

        return [];
    }

    /**
     * The display group for a key. Unknown keys fall into "Other".
     */
    public function groupFor(string $key): string
    {
        foreach ($this->all() as $definition) {
            if ($definition['key'] === $key) {
                return $definition['group'];
            }
        }

        return 'Other';
    }
}
