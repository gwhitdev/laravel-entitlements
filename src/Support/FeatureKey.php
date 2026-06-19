<?php

namespace Entitlements\Support;

use BackedEnum;

class FeatureKey
{
    /**
     * Normalise a feature reference (string key or backed enum case) to its string key.
     */
    public static function normalise(string|BackedEnum $key): string
    {
        return $key instanceof BackedEnum ? (string) $key->value : $key;
    }
}
