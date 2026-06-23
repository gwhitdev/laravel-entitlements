<?php

use Entitlements\Contracts\PlanResolver;
use Entitlements\Models\PlanFeature;
use Entitlements\Models\UserFeature;
use Entitlements\Tests\Fixtures\FakePlanResolver;
use Entitlements\Tests\Fixtures\Feature;
use Entitlements\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

// The resolution cascade — the free core. Order (first match wins):
//   1. admin override  2. explicit per-user grant  3. membership active gate  4. plan-derived.

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('entitlements.enum', Feature::class);

    if (! Schema::hasTable('users')) {
        Schema::create('users', function ($table) {
            $table->id();
            $table->string('name')->nullable();
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });
    }
});

function makeResolver(?string $plan = null, bool $active = false): FakePlanResolver
{
    $fake = new FakePlanResolver($plan, $active);
    app()->instance(PlanResolver::class, $fake);

    return $fake;
}

it('grants every feature to an admin (override at the top of the cascade)', function () {
    config()->set('entitlements.admin_override', true);
    makeResolver();
    $admin = User::create(['is_admin' => true]);

    expect($admin->hasFeature(Feature::AdvancedReporting))->toBeTrue();
    expect($admin->hasFeature('anything_at_all'))->toBeTrue();
});

it('grants a feature via an explicit per-user grant regardless of plan', function () {
    makeResolver(active: false);
    $user = User::create(['is_admin' => false]);
    UserFeature::grant($user, Feature::AdvancedReporting);

    expect($user->hasFeature(Feature::AdvancedReporting))->toBeTrue();
});

it('grants plan-mapped features to an active subscriber', function () {
    makeResolver(plan: 'price_pro', active: true);
    $user = User::create(['is_admin' => false]);
    PlanFeature::grant('price_pro', Feature::AdvancedReporting);

    expect($user->hasFeature(Feature::AdvancedReporting))->toBeTrue();
});

it('denies plan-mapped features when membership is inactive', function () {
    makeResolver(plan: 'price_pro', active: false);
    $user = User::create(['is_admin' => false]);
    PlanFeature::grant('price_pro', Feature::AdvancedReporting);

    expect($user->hasFeature(Feature::AdvancedReporting))->toBeFalse();
});

it('denies features the catalog/plan does not include', function () {
    makeResolver(plan: 'price_pro', active: true);
    $user = User::create(['is_admin' => false]);
    PlanFeature::grant('price_pro', Feature::TeamSeats);

    expect($user->hasFeature(Feature::AdvancedReporting))->toBeFalse();
});

it('memoizes resolution within a request (no repeated queries)', function () {
    $fake = makeResolver(plan: 'price_pro', active: true);
    $user = User::create(['is_admin' => false]);
    PlanFeature::grant('price_pro', Feature::AdvancedReporting);

    $user->hasFeature(Feature::AdvancedReporting);
    $user->hasFeature(Feature::TeamSeats);
    $user->hasFeature(Feature::ApiAccess);

    expect($fake->isActiveCalls)->toBe(1);
});

it('lists all entitlements for a user (grants + active plan)', function () {
    makeResolver(plan: 'price_pro', active: true);
    $user = User::create(['is_admin' => false]);
    PlanFeature::grant('price_pro', Feature::AdvancedReporting);
    $user->grantFeature(Feature::ApiAccess);

    expect($user->features())
        ->toContain('advanced_reporting')
        ->toContain('api_access')
        ->not->toContain('team_seats');
});
