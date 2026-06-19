<?php

namespace Entitlements\Catalog;

use Entitlements\Contracts\FeatureCatalog;
use Illuminate\Support\Str;

/**
 * Default catalog driver — derives the feature set from a consumer's string-backed enum
 * (`config('entitlements.enum')`). The enum may optionally enrich each case with `label()`,
 * `group()`, and `dependencies()` methods; absent those, sensible defaults are used.
 */
class EnumFeatureCatalog implements FeatureCatalog
{
    /** @var class-string|null */
    private ?string $enum;

    public function __construct(?string $enum = null)
    {
        $this->enum = $enum ?? config('entitlements.enum');
    }

    public function all(): array
    {
        if (! $this->enum || ! enum_exists($this->enum)) {
            return [];
        }

        return array_map(fn ($case): array => [
            'key' => (string) $case->value,
            'name' => method_exists($case, 'label') ? $case->label() : Str::headline($case->name),
            'description' => method_exists($case, 'description') ? $case->description() : null,
            'group' => method_exists($case, 'group') ? $case->group() : 'Other',
            'dependencies' => method_exists($case, 'dependencies') ? $case->dependencies() : [],
        ], $this->enum::cases());
    }

    public function has(string $key): bool
    {
        foreach ($this->all() as $definition) {
            if ($definition['key'] === $key) {
                return true;
            }
        }

        return false;
    }

    public function dependenciesFor(string $key): array
    {
        foreach ($this->all() as $definition) {
            if ($definition['key'] === $key) {
                return $definition['dependencies'];
            }
        }

        return [];
    }

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
