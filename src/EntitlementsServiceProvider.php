<?php

namespace Entitlements;

use Entitlements\Contracts\FeatureCatalog;
use Entitlements\Contracts\FeatureGate;
use Entitlements\Contracts\PlanResolver;
use Illuminate\Support\ServiceProvider;

class EntitlementsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/entitlements.php', 'entitlements');

        // Stage 1 wires the default implementations behind these contracts. Each is bound only
        // if the configured class exists, so the package boots cleanly before the impls land and
        // so a consumer can swap any seam (e.g. a Paddle PlanResolver) without touching the rest.
        foreach ([
            FeatureGate::class => 'gate',
            PlanResolver::class => 'resolver',
            FeatureCatalog::class => 'catalog',
        ] as $contract => $configKey) {
            $class = config("entitlements.{$configKey}");

            if (is_string($class) && class_exists($class)) {
                $this->app->singleton($contract, $class);
            }
        }
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/entitlements.php' => $this->app->configPath('entitlements.php'),
            ], 'entitlements-config');

            $this->publishes([
                __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
            ], 'entitlements-migrations');
        }
    }
}
