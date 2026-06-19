<?php

use Entitlements\Models\UserFeature;
use Entitlements\Tests\Fixtures\Feature;
use Entitlements\Tests\Fixtures\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
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

it('renders content when user has the feature', function () {
    $user = User::create(['is_admin' => false]);
    UserFeature::grant($user, Feature::AdvancedReporting);

    $this->actingAs($user);

    $rendered = Blade::render("@feature('advanced_reporting')\nYES\n@endfeature");

    expect(trim($rendered))->toContain('YES');
});

it('omits content when user lacks the feature', function () {
    $user = User::create(['is_admin' => false]);

    $this->actingAs($user);

    $rendered = Blade::render("@feature('advanced_reporting')\nYES\n@endfeature");

    expect(trim($rendered))->not()->toContain('YES');
});

it('omits content for guests', function () {
    $rendered = Blade::render("@feature('advanced_reporting')\nYES\n@endfeature");

    expect(trim($rendered))->not()->toContain('YES');
});
