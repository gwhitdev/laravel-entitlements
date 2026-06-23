<?php

use Entitlements\Models\PlanFeature;
use Entitlements\Models\UserFeature;
use Entitlements\Tests\Fixtures\Feature;
use Entitlements\Tests\Fixtures\User;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

// A user model that opts into the explicit admin hook instead of the is_admin attribute.
class HookUser extends User
{
    protected $table = 'users';

    public bool $hookValue = false;

    public function isEntitlementAdmin(): bool
    {
        return $this->hookValue;
    }
}

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

it('blocks mass-assigning a UserFeature grant (no self-granting via request input)', function () {
    expect(fn () => UserFeature::create(['user_id' => 1, 'feature_key' => 'advanced_reporting']))
        ->toThrow(MassAssignmentException::class);
});

it('blocks mass-assigning a PlanFeature mapping', function () {
    expect(fn () => PlanFeature::create(['plan_identifier' => 'price_x', 'feature_key' => 'advanced_reporting']))
        ->toThrow(MassAssignmentException::class);
});

it('still grants through the controlled grant() path', function () {
    $user = User::create(['is_admin' => false]);

    UserFeature::grant($user, Feature::AdvancedReporting);

    expect(UserFeature::where('user_id', $user->id)->where('feature_key', 'advanced_reporting')->exists())->toBeTrue();
});

it('honors the isEntitlementAdmin hook over the is_admin attribute', function () {
    config()->set('entitlements.admin_override', true);

    // Hook returns true even though is_admin is false → treated as admin (everything unlocked).
    $hookAdmin = new HookUser(['is_admin' => false]);
    $hookAdmin->hookValue = true;
    expect($hookAdmin->hasFeature('anything_at_all'))->toBeTrue();

    // Hook returns false even though is_admin is true → the hook wins, no admin override.
    // Persisted so the (non-admin) cascade can resolve grants by a real id.
    $hookDenied = new HookUser(['is_admin' => true]);
    $hookDenied->hookValue = false;
    $hookDenied->save();
    expect($hookDenied->hasFeature('anything_at_all'))->toBeFalse();
});

it('ships admin override disabled by default (fail-closed)', function () {
    $shipped = require __DIR__.'/../../config/entitlements.php';

    expect($shipped['admin_override'])->toBeFalse();
});

it('does not grant a blanket entitlement when admin override is disabled', function () {
    config()->set('entitlements.admin_override', false);
    $admin = User::create(['is_admin' => true]);

    expect($admin->hasFeature('anything_at_all'))->toBeFalse();
});

it('rejects an unsafe feature name in entitlements:make', function () {
    $this->artisan('entitlements:make', ['name' => "Evil'; system('x'); //"])
        ->assertExitCode(1);
});
