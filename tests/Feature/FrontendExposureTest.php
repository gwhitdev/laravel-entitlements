<?php

use Entitlements\Contracts\PlanResolver;
use Entitlements\Entitlements;
use Entitlements\Models\PlanFeature;
use Entitlements\Models\UserFeature;
use Entitlements\Tests\Fixtures\FakePlanResolver;
use Entitlements\Tests\Fixtures\Feature;
use Entitlements\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

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

it('returns the expected entitlement keys for a user', function () {
    $fake = new FakePlanResolver(plan: 'price_pro', active: true);
    app()->instance(PlanResolver::class, $fake);

    $user = User::create(['is_admin' => false]);
    PlanFeature::grant('price_pro', Feature::AdvancedReporting);
    $user->grantFeature(Feature::ApiAccess);

    $keys = Entitlements::forUser($user);

    expect($keys)->toBeArray()
        ->toContain('advanced_reporting')
        ->toContain('api_access')
        ->not->toContain('team_seats');
});

it('returns every catalog feature for an admin', function () {
    config()->set('entitlements.admin_override', true);

    $fake = new FakePlanResolver;
    app()->instance(PlanResolver::class, $fake);

    $admin = User::create(['is_admin' => true]);

    $keys = Entitlements::forUser($admin);

    expect($keys)->toContain('advanced_reporting', 'team_seats', 'api_access');
});

it('publishes the JS stub via entitlements-js tag', function () {
    $path = resource_path('js/useFeature.ts');

    expect(file_exists($path))->toBeFalse();

    \Illuminate\Support\Facades\Artisan::call('vendor:publish', [
        '--tag' => 'entitlements-js',
    ]);

    expect(file_exists($path))->toBeTrue();

    $contents = file_get_contents($path);

    expect($contents)->toContain('usePage');
    expect($contents)->toContain('useFeature');

    unlink($path);
});
