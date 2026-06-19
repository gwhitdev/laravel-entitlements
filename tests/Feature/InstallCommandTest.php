<?php

it('publishes config and migrations with next-step instructions', function () {
    $this->artisan('entitlements:install')
        ->assertSuccessful()
        ->expectsOutputToContain('HasFeatures')
        ->expectsOutputToContain('migrate')
        ->expectsOutputToContain('entitlements:make');
});
