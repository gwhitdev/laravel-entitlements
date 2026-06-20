<?php

namespace Entitlements;

use Entitlements\Bridge\PennantBridge;
use Entitlements\Console\InstallCommand;
use Entitlements\Console\LintCommand;
use Entitlements\Console\MakeFeatureCommand;
use Entitlements\Contracts\FeatureCatalog;
use Entitlements\Contracts\FeatureGate;
use Entitlements\Contracts\PlanResolver;
use Entitlements\Http\Middleware\EnsureFeature;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Blade;
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

        // Stage 2: DX layer — commands, middleware, Blade, and frontend stub.
        $this->bootMiddleware();
        $this->bootBladeDirective();

        if ($this->app->runningInConsole()) {
            $this->bootCommands();
            $this->bootPublishing();
        }

        $this->bootPennantBridge();
    }

    private function bootMiddleware(): void
    {
        $router = $this->app->make(Router::class);

        $router->aliasMiddleware('feature', EnsureFeature::class);
    }

    private function bootBladeDirective(): void
    {
        Blade::directive('feature', function (string $expression): string {
            return "<?php if((bool) optional(auth()->user())?->hasFeature({$expression})): ?>";
        });

        Blade::directive('endfeature', function (): string {
            return '<?php endif; ?>';
        });
    }

    private function bootCommands(): void
    {
        $this->commands([
            InstallCommand::class,
            LintCommand::class,
            MakeFeatureCommand::class,
        ]);
    }

    private function bootPennantBridge(): void
    {
        if (! config('entitlements.pennant') || ! class_exists(\Laravel\Pennant\Feature::class)) {
            return;
        }

        $this->app->booted(function (): void {
            $this->app->make(PennantBridge::class)->register();
        });
    }

    private function bootPublishing(): void
    {
        $this->publishes([
            __DIR__.'/../config/entitlements.php' => $this->app->configPath('entitlements.php'),
        ], 'entitlements-config');

        $this->publishes([
            __DIR__.'/../database/migrations' => $this->app->databasePath('migrations'),
        ], 'entitlements-migrations');

        $this->publishes([
            __DIR__.'/../resources/js/useFeature.ts' => $this->app->resourcePath('js/useFeature.ts'),
        ], 'entitlements-js');
    }
}
