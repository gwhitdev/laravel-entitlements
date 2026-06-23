<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// Stage 0 green gate: the package boots, config merges, and the schema migrates cleanly —
// before any default implementation exists. The contract bindings are skipped while their
// configured classes are absent (see EntitlementsServiceProvider), so this must stay green.

it('boots with the published config defaults', function () {
    expect(config('entitlements.plan_store'))->toBe('database');
    expect(config('entitlements.admin_override'))->toBeFalse();
});

it('migrates the three entitlement tables', function () {
    expect(Schema::hasTable('features'))->toBeTrue();
    expect(Schema::hasTable('plan_features'))->toBeTrue();
    expect(Schema::hasTable('user_features'))->toBeTrue();
});

it('names the plan mapping column opaquely (not stripe-specific)', function () {
    expect(Schema::hasColumn('plan_features', 'plan_identifier'))->toBeTrue();
    expect(Schema::hasColumn('plan_features', 'feature_key'))->toBeTrue();
    expect(Schema::hasColumn('plan_features', 'stripe_price_id'))->toBeFalse();
});
