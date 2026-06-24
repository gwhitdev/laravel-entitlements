# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Commands

```bash
composer test                     # run the full test suite
vendor/bin/pest tests/Feature/ResolutionCascadeSpecTest.php  # run a single test file
vendor/bin/pest --filter "grants every feature to an admin"  # run a single test by name
composer audit                    # check for vulnerable dependencies
```

## Architecture

This is a Laravel package. There is no application entrypoint — the package is consumed by a host Laravel app via `composer require`.

### The single public surface

`FeatureGate` (`src/Contracts/FeatureGate.php`) is the only public contract. Everything else — the cascade, memoization, billing seam, catalog — lives behind it. The `Tessera` facade resolves to this contract.

### Resolution cascade

`CascadingFeatureGate` (`src/Gate/`) resolves entitlements in order (first match wins):

1. Admin override — `isEntitlementAdmin()` on the user, or `is_admin` attribute as fallback; only active when `config('entitlements.admin_override')` is `true` (off by default, fail-closed).
2. Per-user grant — a row in `user_features` for this user + key.
3. Membership gate — `PlanResolver::isActive()` must return `true`; if not, resolution stops here.
4. Plan mapping — the user's plan identifier is looked up in `plan_features` (DB store) or `config('entitlements.plans')` (config store).

Results are memoized per user per request on the gate instance, which is bound `scoped` (not singleton) so Octane/queue workers flush it at each boundary.

### Three swappable seams

All three are bound from `config/entitlements.php` and resolved through the container:

| Seam | Contract | Default |
|------|----------|---------|
| Billing | `PlanResolver` | `StripePlanResolver` — reads Cashier's `stripe_price` via `method_exists` (no hard Cashier dependency) |
| Feature catalog | `FeatureCatalog` | `EnumFeatureCatalog` — derives features from a string-backed enum at `config('entitlements.enum')` |
| Gate | `FeatureGate` | `CascadingFeatureGate` |

`ConfigFeatureCatalog` is an alternative catalog driver reading `config('entitlements.features')` instead of an enum.

### Models

Both `UserFeature` and `PlanFeature` are fully guarded (`$guarded = ['*']`). Always write through their static `grant()` methods — never `create()` or mass assignment.

### Optional Pennant bridge

`src/Bridge/PennantBridge.php` — call `PennantBridge::register()` once in `AppServiceProvider::boot()` to register every catalog feature with Pennant, delegating resolution to the cascade. Entirely opt-in; not wired by the service provider.

### Test structure

All tests are in `tests/Feature/`. There are no unit tests — the suite tests behaviour through the public `HasFeatures` trait and `FeatureGate` contract. `tests/Fixtures/` contains a `FakePlanResolver`, a fixture `Feature` enum, and a minimal `User` model used across the suite.

The CI matrix (`tests.yml`) runs PHP 8.2–8.4 × Laravel 11–12. A separate `security` job runs `composer audit` on every push.
