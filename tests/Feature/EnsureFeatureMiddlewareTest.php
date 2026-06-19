<?php

use Entitlements\Tests\Fixtures\Feature;
use Entitlements\Tests\Fixtures\User;
use Entitlements\Models\UserFeature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('app.key', 'base64:'.base64_encode(random_bytes(32)));
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

it('aborts 403 when user lacks the feature', function () {
    Route::middleware(['web', 'feature:advanced_reporting'])->get('/secret', fn () => 'ok');

    $user = User::create(['is_admin' => false]);

    $this->actingAs($user)->get('/secret')->assertForbidden();
});

it('allows access when user has the feature via grant', function () {
    Route::middleware(['web', 'feature:advanced_reporting'])->get('/secret', fn () => 'ok');

    $user = User::create(['is_admin' => false]);
    UserFeature::grant($user, Feature::AdvancedReporting);

    $this->actingAs($user)->get('/secret')->assertOk();
});

it('aborts 403 for guests (no authenticated user)', function () {
    Route::middleware(['web', 'feature:advanced_reporting'])->get('/secret', fn () => 'ok');

    $this->get('/secret')->assertForbidden();
});
