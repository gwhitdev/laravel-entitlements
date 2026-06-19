<?php

use Entitlements\Contracts\FeatureCatalog;
use Entitlements\Contracts\FeatureGate;
use Entitlements\Contracts\PlanResolver;
use Entitlements\Gate\CascadingFeatureGate;
use Entitlements\Tests\Fixtures\FakeFeatureCatalog;
use Entitlements\Tests\Fixtures\FakePlanResolver;
use Entitlements\Tests\Fixtures\Feature;
use Entitlements\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('entitlements.enum', Feature::class);
    config()->set('entitlements.admin_override', false);

    if (! Schema::hasTable('users')) {
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });
    }
});

function makeGate(array $catalogFeatures, ?string $plan = null, bool $active = false): CascadingFeatureGate
{
    $catalog = new FakeFeatureCatalog($catalogFeatures);
    $resolver = new FakePlanResolver($plan, $active);

    app()->instance(FeatureCatalog::class, $catalog);
    app()->instance(PlanResolver::class, $resolver);
    app()->singleton(FeatureGate::class, CascadingFeatureGate::class);

    return app(FeatureGate::class);
}

it('grants a feature with no dependencies normally', function () {
    $gate = makeGate(catalogFeatures: [
        ['key' => 'advanced_reporting'],
    ], plan: 'price_pro', active: true);

    $user = User::create(['is_admin' => false]);

    \Entitlements\Models\PlanFeature::grant('price_pro', 'advanced_reporting');

    expect($gate->has($user, 'advanced_reporting'))->toBeTrue();
});

it('grants a feature when its dependencies are satisfied', function () {
    $gate = makeGate(catalogFeatures: [
        ['key' => 'advanced_reporting', 'dependencies' => ['api_access']],
        ['key' => 'api_access'],
    ], plan: 'price_pro', active: true);

    $user = User::create(['is_admin' => false]);

    \Entitlements\Models\PlanFeature::grant('price_pro', 'advanced_reporting');
    \Entitlements\Models\PlanFeature::grant('price_pro', 'api_access');

    expect($gate->has($user, 'advanced_reporting'))->toBeTrue();
});

it('denies a feature when a dependency is unsatisfied', function () {
    $gate = makeGate(catalogFeatures: [
        ['key' => 'advanced_reporting', 'dependencies' => ['api_access']],
        ['key' => 'api_access'],
    ], plan: 'price_pro', active: true);

    $user = User::create(['is_admin' => false]);

    \Entitlements\Models\PlanFeature::grant('price_pro', 'advanced_reporting');

    expect($gate->has($user, 'advanced_reporting'))->toBeFalse();
});

it('resolves transitive dependencies (A → B → C)', function () {
    $gate = makeGate(catalogFeatures: [
        ['key' => 'a', 'dependencies' => ['b']],
        ['key' => 'b', 'dependencies' => ['c']],
        ['key' => 'c'],
    ], plan: 'price_pro', active: true);

    $user = User::create(['is_admin' => false]);

    \Entitlements\Models\PlanFeature::grant('price_pro', 'a');
    \Entitlements\Models\PlanFeature::grant('price_pro', 'b');
    \Entitlements\Models\PlanFeature::grant('price_pro', 'c');

    expect($gate->has($user, 'a'))->toBeTrue();

    // Break the chain: remove C
    \Entitlements\Models\PlanFeature::query()->where('feature_key', 'c')->delete();

    app()->forgetInstance(FeatureGate::class);
    $gate2 = makeGate(catalogFeatures: [
        ['key' => 'a', 'dependencies' => ['b']],
        ['key' => 'b', 'dependencies' => ['c']],
        ['key' => 'c'],
    ], plan: 'price_pro', active: true);

    expect($gate2->has($user, 'a'))->toBeFalse();
});

it('does not loop infinitely on circular dependencies', function () {
    $gate = makeGate(catalogFeatures: [
        ['key' => 'a', 'dependencies' => ['b']],
        ['key' => 'b', 'dependencies' => ['a']],
    ], plan: 'price_pro', active: true);

    $user = User::create(['is_admin' => false]);

    \Entitlements\Models\PlanFeature::grant('price_pro', 'a');
    \Entitlements\Models\PlanFeature::grant('price_pro', 'b');

    expect($gate->has($user, 'a'))->toBeTrue();
    expect($gate->has($user, 'b'))->toBeTrue();
});

it('includes transitively-resolved dependency keys in entitlements()', function () {
    $gate = makeGate(catalogFeatures: [
        ['key' => 'a', 'dependencies' => ['b']],
        ['key' => 'b', 'dependencies' => ['c']],
        ['key' => 'c'],
        ['key' => 'd'],
    ], plan: 'price_pro', active: true);

    $user = User::create(['is_admin' => false]);

    \Entitlements\Models\PlanFeature::grant('price_pro', 'a');
    \Entitlements\Models\PlanFeature::grant('price_pro', 'b');
    \Entitlements\Models\PlanFeature::grant('price_pro', 'c');

    $entitlements = $gate->entitlements($user);

    expect($entitlements)->toContain('a', 'b', 'c');
});
