<?php

use Entitlements\Catalog\ConfigFeatureCatalog;
use Entitlements\Contracts\FeatureCatalog;
use Entitlements\Models\PlanFeature;
use Entitlements\Support\PrerequisiteChecker;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Use ConfigFeatureCatalog with a dependency: year_end_pack needs expense_tracking.
    config()->set('entitlements.catalog', ConfigFeatureCatalog::class);
    config()->set('entitlements.features', [
        ['key' => 'expense_tracking', 'group' => 'Finance'],
        ['key' => 'year_end_pack', 'group' => 'Finance', 'dependencies' => ['expense_tracking']],
        ['key' => 'advanced_reporting', 'group' => 'Reporting'],
    ]);
    config()->set('entitlements.plan_store', 'database');

    app()->singleton(FeatureCatalog::class, ConfigFeatureCatalog::class);
});

it('reports a missing prerequisite when a plan has the dependent feature but not its prerequisite', function () {
    PlanFeature::grant('price_pro', 'year_end_pack');
    // expense_tracking is NOT granted to price_pro.

    $checker = new PrerequisiteChecker(app(FeatureCatalog::class));
    $issues = $checker->forPlan('price_pro');

    expect($issues)->toHaveKey('year_end_pack');
    expect($issues['year_end_pack'])->toContain('expense_tracking');
});

it('reports no issues when the prerequisite is also mapped to the plan', function () {
    PlanFeature::grant('price_pro', 'year_end_pack');
    PlanFeature::grant('price_pro', 'expense_tracking');

    $checker = new PrerequisiteChecker(app(FeatureCatalog::class));
    $issues = $checker->forPlan('price_pro');

    expect($issues)->toBe([]);
});

it('all() returns issues across every plan omitting plans with no problems', function () {
    // price_pro is missing expense_tracking
    PlanFeature::grant('price_pro', 'year_end_pack');

    // price_basic is clean (only has advanced_reporting which has no deps)
    PlanFeature::grant('price_basic', 'advanced_reporting');

    $checker = new PrerequisiteChecker(app(FeatureCatalog::class));
    $all = $checker->all();

    expect($all)->toHaveKey('price_pro');
    expect($all)->not->toHaveKey('price_basic');
    expect($all['price_pro'])->toHaveKey('year_end_pack');
});

it('all() returns empty array when every plan is clean', function () {
    PlanFeature::grant('price_pro', 'year_end_pack');
    PlanFeature::grant('price_pro', 'expense_tracking');

    $checker = new PrerequisiteChecker(app(FeatureCatalog::class));

    expect($checker->all())->toBe([]);
});

it('forPlan() works with the config plan_store', function () {
    config()->set('entitlements.plan_store', 'config');
    config()->set('entitlements.plans', [
        // year_end_pack depends on expense_tracking, which is not in this plan.
        'price_config_plan' => ['year_end_pack'],
    ]);

    $checker = new PrerequisiteChecker(app(FeatureCatalog::class));
    $issues = $checker->forPlan('price_config_plan');

    expect($issues)->toHaveKey('year_end_pack');
    expect($issues['year_end_pack'])->toContain('expense_tracking');
});

it('all() works with the config plan_store', function () {
    config()->set('entitlements.plan_store', 'config');
    config()->set('entitlements.plans', [
        'price_full' => ['year_end_pack', 'expense_tracking'],
        'price_lite' => ['year_end_pack'],  // missing expense_tracking
    ]);

    $checker = new PrerequisiteChecker(app(FeatureCatalog::class));
    $all = $checker->all();

    expect($all)->not->toHaveKey('price_full');
    expect($all)->toHaveKey('price_lite');
});
