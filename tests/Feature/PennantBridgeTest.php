<?php

use Entitlements\Bridge\PennantBridge;
use Entitlements\Contracts\PlanResolver;
use Entitlements\Models\PlanFeature;
use Entitlements\Models\UserFeature;
use Entitlements\Tests\Fixtures\FakePlanResolver;
use Entitlements\Tests\Fixtures\Feature;
use Entitlements\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Laravel\Pennant\Feature as Pennant;

// Pennant is an optional dependency — skip this suite if it isn't installed.
if (! class_exists(Pennant::class)) {
    return;
}

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('entitlements.enum', Feature::class);
    config()->set('pennant.default', 'array');
    config()->set('pennant.stores', ['array' => ['driver' => 'array']]);

    if (! Schema::hasTable('users')) {
        Schema::create('users', function ($table) {
            $table->id();
            $table->boolean('is_admin')->default(false);
            $table->timestamps();
        });
    }
});

it('registers all catalog features with Pennant', function () {
    PennantBridge::register();

    $defined = Pennant::defined();

    expect($defined)->toContain('advanced_reporting')
        ->toContain('team_seats')
        ->toContain('api_access');
});

it('delegates Pennant active() checks to the entitlement cascade', function () {
    $resolver = new FakePlanResolver(plan: 'price_pro', active: true);
    app()->instance(PlanResolver::class, $resolver);

    PlanFeature::grant('price_pro', Feature::AdvancedReporting);
    $user = User::create(['is_admin' => false]);

    PennantBridge::register();

    expect(Pennant::for($user)->active('advanced_reporting'))->toBeTrue();
    expect(Pennant::for($user)->active('team_seats'))->toBeFalse();
});

it('returns false for a non-Authenticatable scope', function () {
    PennantBridge::register();

    // Pennant supports non-user scopes; the bridge must not throw.
    expect(Pennant::for('some-non-user-scope')->active('advanced_reporting'))->toBeFalse();
});

it('respects per-user grants through the Pennant API', function () {
    $resolver = new FakePlanResolver(active: false);
    app()->instance(PlanResolver::class, $resolver);

    $user = User::create(['is_admin' => false]);
    UserFeature::grant($user, Feature::TeamSeats);

    PennantBridge::register();

    expect(Pennant::for($user)->active('team_seats'))->toBeTrue();
});
