<?php

use Entitlements\Catalog\ConfigFeatureCatalog;
use Entitlements\Contracts\FeatureCatalog;
use Entitlements\Models\PlanFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('entitlements.catalog', ConfigFeatureCatalog::class);
    config()->set('entitlements.features', [
        ['key' => 'expense_tracking', 'group' => 'Finance'],
        ['key' => 'year_end_pack', 'group' => 'Finance', 'dependencies' => ['expense_tracking']],
    ]);
    config()->set('entitlements.plan_store', 'database');

    app()->singleton(FeatureCatalog::class, ConfigFeatureCatalog::class);
});

it('exits with failure and prints a warning when a plan is missing a prerequisite', function () {
    PlanFeature::grant('price_pro', 'year_end_pack');
    // expense_tracking not granted — prerequisite unmet.

    $this->artisan('entitlements:lint')
        ->assertFailed()
        ->expectsOutputToContain('missing prerequisite');
});

it('includes the plan and feature names in the warning output', function () {
    PlanFeature::grant('price_pro', 'year_end_pack');

    $this->artisan('entitlements:lint')
        ->assertFailed()
        ->expectsOutputToContain('missing prerequisite');
});

it('exits successfully with a clean message when all prerequisites are satisfied', function () {
    PlanFeature::grant('price_pro', 'year_end_pack');
    PlanFeature::grant('price_pro', 'expense_tracking');

    $this->artisan('entitlements:lint')
        ->assertSuccessful()
        ->expectsOutputToContain('All plans satisfy their declared prerequisites');
});

it('exits successfully when no plans exist at all', function () {
    // No PlanFeature rows => nothing to check.
    $this->artisan('entitlements:lint')
        ->assertSuccessful()
        ->expectsOutputToContain('All plans satisfy their declared prerequisites');
});
