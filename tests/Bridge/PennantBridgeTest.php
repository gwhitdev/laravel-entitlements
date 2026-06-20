<?php

use Entitlements\Bridge\PennantBridge;
use Entitlements\Catalog\ConfigFeatureCatalog;
use Entitlements\Contracts\FeatureCatalog;
use Entitlements\Contracts\FeatureGate;
use Entitlements\Contracts\PlanResolver;
use Entitlements\Gate\CascadingFeatureGate;
use Entitlements\Tests\Fixtures\FakePlanResolver;
use Entitlements\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Pennant\Feature;

uses(RefreshDatabase::class);

beforeEach(function () {
    // Use Pennant's in-memory driver to avoid a DB schema conflict with the entitlements
    // 'features' table (which has a different shape than Pennant's persistence table).
    config()->set('pennant.default', 'array');
    Feature::forgetDrivers();

    config()->set('entitlements.features', [
        ['key' => 'advanced_reporting', 'group' => 'Reporting'],
        ['key' => 'team_seats', 'group' => 'Teams'],
    ]);

    app()->instance(FeatureCatalog::class, new ConfigFeatureCatalog());

    if (! Schema::hasTable('users')) {
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });
    }
});

function buildBridge(?string $plan = null, bool $active = false): PennantBridge
{
    $fake = new FakePlanResolver($plan, $active);
    app()->instance(PlanResolver::class, $fake);

    $catalog = app(FeatureCatalog::class);
    $gate = new CascadingFeatureGate($fake, $catalog);
    app()->instance(FeatureGate::class, $gate);

    $bridge = new PennantBridge($catalog, $gate);
    $bridge->register();

    return $bridge;
}

it('registers all catalog features in Pennant', function () {
    buildBridge();

    expect(Feature::defined())
        ->toContain('advanced_reporting')
        ->toContain('team_seats');
});

it('returns true via Pennant for a feature included in the user\'s active plan', function () {
    buildBridge(plan: 'price_pro', active: true);

    $user = User::create(['is_admin' => false]);
    \Entitlements\Models\PlanFeature::grant('price_pro', 'advanced_reporting');

    expect(Feature::for($user)->active('advanced_reporting'))->toBeTrue();
    expect(Feature::for($user)->active('team_seats'))->toBeFalse();
});

it('returns false via Pennant when the user\'s membership is inactive', function () {
    buildBridge(plan: 'price_pro', active: false);

    $user = User::create(['is_admin' => false]);
    \Entitlements\Models\PlanFeature::grant('price_pro', 'advanced_reporting');

    expect(Feature::for($user)->active('advanced_reporting'))->toBeFalse();
});

it('returns true via Pennant for an explicit per-user grant regardless of plan', function () {
    buildBridge(active: false);

    $user = User::create(['is_admin' => false]);
    \Entitlements\Models\UserFeature::grant($user, 'advanced_reporting');

    expect(Feature::for($user)->active('advanced_reporting'))->toBeTrue();
});

it('returns false via Pennant for a non-authenticatable scope', function () {
    buildBridge();

    expect(Feature::for('not-a-user')->active('advanced_reporting'))->toBeFalse();
});
