<?php

use Entitlements\Catalog\ConfigFeatureCatalog;

beforeEach(function () {
    config()->set('entitlements.features', []);
});

it('returns an empty array when no features are configured', function () {
    $catalog = new ConfigFeatureCatalog();

    expect($catalog->all())->toBe([]);
});

it('normalises every entry to the full five-key contract shape', function () {
    config()->set('entitlements.features', [
        ['key' => 'advanced_reporting', 'group' => 'Reporting', 'dependencies' => ['expense_tracking']],
    ]);

    $catalog = new ConfigFeatureCatalog();
    $all = $catalog->all();

    expect($all)->toHaveCount(1);

    $entry = $all[0];
    expect($entry)->toHaveKeys(['key', 'name', 'description', 'group', 'dependencies']);
    expect($entry['key'])->toBe('advanced_reporting');
    expect($entry['name'])->toBe('Advanced Reporting');  // Str::headline default
    expect($entry['description'])->toBeNull();           // default
    expect($entry['group'])->toBe('Reporting');
    expect($entry['dependencies'])->toBe(['expense_tracking']);
});

it('applies explicit name and description when provided', function () {
    config()->set('entitlements.features', [
        [
            'key' => 'team_seats',
            'name' => 'Team Seats (custom)',
            'description' => 'Allow multiple seats.',
            'group' => 'Teams',
            'dependencies' => [],
        ],
    ]);

    $catalog = new ConfigFeatureCatalog();
    $entry = $catalog->all()[0];

    expect($entry['name'])->toBe('Team Seats (custom)');
    expect($entry['description'])->toBe('Allow multiple seats.');
});

it('applies defaults for name, description, group, and dependencies when absent', function () {
    config()->set('entitlements.features', [
        ['key' => 'api_access'],
    ]);

    $catalog = new ConfigFeatureCatalog();
    $entry = $catalog->all()[0];

    expect($entry['name'])->toBe('Api Access');
    expect($entry['description'])->toBeNull();
    expect($entry['group'])->toBe('Other');
    expect($entry['dependencies'])->toBe([]);
});

it('has() returns true for a configured key', function () {
    config()->set('entitlements.features', [
        ['key' => 'advanced_reporting'],
    ]);

    $catalog = new ConfigFeatureCatalog();

    expect($catalog->has('advanced_reporting'))->toBeTrue();
    expect($catalog->has('unknown_key'))->toBeFalse();
});

it('dependenciesFor() returns the declared dependencies for a key', function () {
    config()->set('entitlements.features', [
        ['key' => 'year_end_pack', 'dependencies' => ['expense_tracking', 'advanced_reporting']],
        ['key' => 'expense_tracking'],
    ]);

    $catalog = new ConfigFeatureCatalog();

    expect($catalog->dependenciesFor('year_end_pack'))->toBe(['expense_tracking', 'advanced_reporting']);
    expect($catalog->dependenciesFor('expense_tracking'))->toBe([]);
    expect($catalog->dependenciesFor('unknown_key'))->toBe([]);
});

it('groupFor() returns the declared group or "Other" for unknown keys', function () {
    config()->set('entitlements.features', [
        ['key' => 'advanced_reporting', 'group' => 'Reporting'],
        ['key' => 'team_seats'],
    ]);

    $catalog = new ConfigFeatureCatalog();

    expect($catalog->groupFor('advanced_reporting'))->toBe('Reporting');
    expect($catalog->groupFor('team_seats'))->toBe('Other');
    expect($catalog->groupFor('nonexistent'))->toBe('Other');
});
