# Stage 2 Implementation Prompt — entitlements-for-laravel (DX / batteries-included layer)

You are implementing **Stage 2** of the `gwhitdev/entitlements-for-laravel` Laravel package. The repository root you are in IS the package. Work with strict TDD: extend Pest tests, run `vendor/bin/pest`, never finish red.

## Absolute rules
1. **Do NOT modify anything in `src/Contracts/`** — those three interfaces (`PlanResolver`, `FeatureCatalog`, `FeatureGate`) are frozen seams. Build behind them.
2. **Keep the existing 10 tests passing** (`tests/Feature/SpecBootTest.php`, `tests/Feature/ResolutionCascadeSpecTest.php`). Run `vendor/bin/pest` before you start and after every change. Done = green.
3. Match existing conventions exactly: namespace `Entitlements\` (PSR-4 → `src/`); tests `Entitlements\Tests\` → `tests/`; 4-space indent; typed params + return types; PHPDoc on public methods; plain `<?php` (no `declare(strict_types=1)`, matching the existing files).
4. This is a **package, not an app** — there is no `php artisan`. Test console commands with `$this->artisan('name')` and HTTP with in-test routes, all via Orchestra Testbench. The base test class is `Entitlements\Tests\TestCase` (already wired; auto-applied by `tests/Pest.php`).
5. **No new runtime dependencies.** `orchestra/testbench` and `pestphp/pest` are already dev deps. `laravel/cashier` + `laravel/pennant` are dev/suggest only — do not require them at runtime.

## What already exists (build ON this; do not re-implement)
- Contracts in `src/Contracts/`: `PlanResolver` (`planIdentifier($user): ?string`, `isActive($user): bool`), `FeatureCatalog` (`all()`, `has($key)`, `dependenciesFor($key)`, `groupFor($key)`), `FeatureGate` (`has($user,$key): bool`, `entitlements($user): array`).
- `Entitlements\Gate\CascadingFeatureGate` — bound to `FeatureGate`; does the cascade (admin → per-user grant → membership-active → plan-derived), memoized.
- `Entitlements\Resolvers\StripePlanResolver`, `Entitlements\Catalog\EnumFeatureCatalog`.
- `Entitlements\Concerns\HasFeatures` trait → `hasFeature(string|BackedEnum): bool`, `features(): array`, `grantFeature(string|BackedEnum): void`.
- `Entitlements\Models\PlanFeature` (`PlanFeature::grant($planIdentifier, $feature)`) and `UserFeature` (`UserFeature::grant($user, $feature)`).
- `Entitlements\Support\FeatureKey::normalise(string|BackedEnum): string`.
- `Entitlements\EntitlementsServiceProvider` — merges `config/entitlements.php`, loads + publishes migrations (tag `entitlements-migrations`) and config (tag `entitlements-config`); binds each contract from config **only when the configured class exists**.
- `config/entitlements.php` keys: `gate`, `resolver`, `catalog`, `enum` (consumer enum class), `plan_store` (`'database'`|`'config'`), `plans`, `admin_override`.
- Fixtures in `tests/Fixtures/`: `User` (uses `HasFeatures`, has boolean `is_admin`), `Feature` enum (`AdvancedReporting='advanced_reporting'`, `TeamSeats='team_seats'`, `ApiAccess='api_access'`), `FakePlanResolver(?string $plan, bool $active)` with a public `$isActiveCalls` counter.
- **Test setup pattern** (copy from `ResolutionCascadeSpecTest.php`): `uses(RefreshDatabase::class);` runs the package migrations; create a `users` table in `beforeEach` (`if (! Schema::hasTable('users'))`); bind a fake resolver with `app()->instance(PlanResolver::class, new FakePlanResolver(...))`; set `config()->set('entitlements.enum', Feature::class)`.

## Deliverables — register everything in `EntitlementsServiceProvider::boot()`

### 1. `entitlements:install` — `src/Console/InstallCommand.php`
- `protected $signature = 'entitlements:install';`
- Publishes config + migrations by calling `$this->callSilent('vendor:publish', ['--tag' => 'entitlements-config'])` and again for `entitlements-migrations`.
- Prints next steps: add `use Entitlements\Concerns\HasFeatures;` to the `User` model → run `php artisan migrate` → `php artisan entitlements:make YourFeature`.
- `return self::SUCCESS;`
- Register via `$this->commands([...])` inside the provider's `runningInConsole()` block.
- **Test** `tests/Feature/InstallCommandTest.php`: `$this->artisan('entitlements:install')->assertSuccessful();` and `->expectsOutputToContain('HasFeatures')`.

### 2. `entitlements:make` — `src/Console/MakeFeatureCommand.php`
- `protected $signature = 'entitlements:make {name : StudlyCase feature name} {--group=Other}';`
- Compute `$studly = Str::studly($name)` and `$value = Str::snake($name)`.
- Resolve the enum class from `config('entitlements.enum')`. If it exists, get its file via `(new ReflectionClass($enum))->getFileName()`. If it does NOT exist, create the file at `app_path('Enums/'.class_basename($enum).'.php')` with `namespace App\Enums; enum <Name>: string {}`.
- Insert `    case {$studly} = '{$value}';` immediately before the enum's final closing `}`. **Idempotent**: if a line containing `'{$value}'` already exists, print "already exists" and return SUCCESS without editing.
- Print the snippet + reminder: `PlanFeature::grant('your_price_id', Feature::{$studly});`.
- **Test** `tests/Feature/MakeFeatureCommandTest.php`: in the test, write a temporary enum file (e.g. under `sys_get_temp_dir()` or a tmp path), point `config('entitlements.enum')` at a class whose file you control, run the command, assert the case line was appended, run again, assert it was NOT duplicated. Clean up the temp file. **Do not mutate the real `tests/Fixtures/Feature.php`.**
- If editing the enum file proves too fragile, you MAY instead implement `make` to insert into a `features` table via a new `Entitlements\Models\Feature` model (the `features` table already exists). Prefer the enum approach; if you fall back, say so in your summary.

### 3. `feature` route middleware — `src/Http/Middleware/EnsureFeature.php`
- `public function handle(Request $request, Closure $next, string $key)`: `$user = $request->user();` if `$user === null || ! $user->hasFeature($key)` → `abort(403);` else `return $next($request);`.
- Register the alias in provider boot: resolve `Illuminate\Routing\Router` from the container and call `$router->aliasMiddleware('feature', EnsureFeature::class);`.
- **Test** `tests/Feature/EnsureFeatureMiddlewareTest.php` (use the ResolutionCascadeSpecTest setup pattern): define `Route::middleware(['web','feature:advanced_reporting'])->get('/secret', fn () => 'ok');` inside the test; `actingAs($user)` who lacks it → assertForbidden(); after `$user->grantFeature(Feature::AdvancedReporting)` (or `UserFeature::grant`) → assertOk(). Bind a `FakePlanResolver` so no billing is needed.

### 4. Blade `@feature` directive
- In provider boot: `Blade::if('feature', fn (string $key): bool => (bool) optional(auth()->user())?->hasFeature($key));`. `Blade::if` auto-creates the matching `@endfeature`.
- **Test** `tests/Feature/BladeFeatureDirectiveTest.php`: `actingAs` a user, then `Blade::render("@feature('advanced_reporting')YES@endfeature")` → assert it contains/omits `YES` for entitled/not-entitled users.

### 5. Frontend exposure (Inertia/React)
- Add `src/Entitlements.php` with `public static function forUser(\Illuminate\Contracts\Auth\Authenticatable $user): array { return app(\Entitlements\Contracts\FeatureGate::class)->entitlements($user); }` (the array of entitlement keys to share as an Inertia prop). Keep it tiny.
- Add `resources/js/useFeature.ts`: a hook reading `usePage().props.features as string[]` and returning `(key: string) => features.includes(key)`. (Plain TS; it's a publishable stub, not compiled here.)
- Add a publish tag `entitlements-js` in the provider mapping `__DIR__.'/../resources/js/useFeature.ts'` → `resource_path('js/useFeature.ts')`.
- **Test** `tests/Feature/FrontendExposureTest.php`: assert `Entitlements::forUser($user)` returns the expected keys (set up like the cascade test); and assert the stub file exists at `resources/js/useFeature.ts`. Do NOT execute JS.

## Conventions reminder
- Commands extend `Illuminate\Console\Command` with `protected $signature` / `protected $description` properties (NOT attributes).
- Use `Illuminate\Support\Str` for case conversion.
- Treat a guest (no authenticated user) as **not entitled** everywhere — never throw on a missing user.
- Small, PHPDoc'd methods matching the existing style.

## Definition of done
- [ ] All five deliverables implemented and registered in `EntitlementsServiceProvider`.
- [ ] A new test file per deliverable (names above), plus the existing 10 — **all green** under `vendor/bin/pest`.
- [ ] `src/Contracts/` untouched; no new runtime dependencies.
- [ ] Add a short "Stage 2 (DX)" section to `SPEC.md` listing what shipped.
- [ ] Commit with a clear message. Do NOT add any `Co-Authored-By` trailer.

When finished, summarise what you built and paste the final `vendor/bin/pest` summary line.
