<?php

use Entitlements\Catalog\ConfigFeatureCatalog;
use Entitlements\Contracts\FeatureCatalog;

beforeEach(function () {
    $this->catalog = new ConfigFeatureCatalog;
});

it('returns all configured features', function () {
    config()->set('entitlements.features', [
        ['key' => 'advanced_reporting', 'group' => 'Reporting', 'dependencies' => []],
        ['key' => 'api_access', 'group' => 'Integrations', 'dependencies' => []],
    ]);

    $all = $this->catalog->all();

    expect($all)->toBeArray()->toHaveCount(2);
    expect($all[0]['key'])->toBe('advanced_reporting');
    expect($all[1]['key'])->toBe('api_access');
});

it('returns empty array when no features configured', function () {
    config()->set('entitlements.features', []);

    expect($this->catalog->all())->toBe([]);
});

it('checks if a feature key exists', function () {
    config()->set('entitlements.features', [
        ['key' => 'dark_mode'],
    ]);

    expect($this->catalog->has('dark_mode'))->toBeTrue();
    expect($this->catalog->has('nonexistent'))->toBeFalse();
});

it('returns dependencies for a feature key', function () {
    config()->set('entitlements.features', [
        ['key' => 'advanced_reporting', 'dependencies' => ['api_access', 'team_seats']],
        ['key' => 'api_access', 'dependencies' => []],
    ]);

    expect($this->catalog->dependenciesFor('advanced_reporting'))->toBe(['api_access', 'team_seats']);
    expect($this->catalog->dependenciesFor('api_access'))->toBe([]);
    expect($this->catalog->dependenciesFor('nonexistent'))->toBe([]);
});

it('returns group for a feature key', function () {
    config()->set('entitlements.features', [
        ['key' => 'advanced_reporting', 'group' => 'Reporting'],
        ['key' => 'api_access'],
    ]);

    expect($this->catalog->groupFor('advanced_reporting'))->toBe('Reporting');
    expect($this->catalog->groupFor('api_access'))->toBe('Other');
    expect($this->catalog->groupFor('nonexistent'))->toBe('Other');
});

it('integrates with CascadingFeatureGate for dependency resolution', function () {
    $this->markTestSkipped('Integration tested fully in DependencyResolutionTest');
});
