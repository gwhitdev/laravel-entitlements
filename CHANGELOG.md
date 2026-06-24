# Changelog

All notable changes to this project will be documented in this file.

## [0.2.0] — 2026-06-24

### Added
- **Pennant bridge** (`Entitlements\Bridge\PennantBridge`) — opt-in registration of all catalog features with Laravel Pennant, delegating resolution to the entitlement cascade. Call `PennantBridge::register()` in `AppServiceProvider::boot()` to enable.
- `AGENTS.md` — instructions for AI assistants to gate features correctly in one prompt.
- `SECURITY.md` — vulnerability disclosure policy.
- `composer test` script (`vendor/bin/pest`).
- CI/Packagist/license badges in README.

## [0.1.0] — 2026-06-24

Initial alpha release.

### Added
- **Core engine** — `CascadingFeatureGate` with a four-step resolution cascade: admin override → per-user grant → membership gate → plan mapping. Results memoized per user per request.
- **`HasFeatures` trait** — `hasFeature()`, `features()`, `grantFeature()` on the User model.
- **`Tessera` facade** — `Tessera::has()`, `Tessera::entitlements()`.
- **Three swappable seams** — `FeatureGate`, `PlanResolver` (default: `StripePlanResolver` via Cashier), `FeatureCatalog` (default: `EnumFeatureCatalog`; alternative: `ConfigFeatureCatalog`).
- **`feature:` route middleware** — aborts 403 unless the authenticated user is entitled.
- **`@feature` / `@endfeature` Blade directives**.
- **Artisan commands** — `entitlements:install`, `entitlements:make`, `entitlements:lint`.
- **Declarative dependencies** — prerequisite declarations on enum cases, enforced by `entitlements:lint`.
- **Security hardening** — fully guarded models (`UserFeature`, `PlanFeature`), fail-closed admin override, input sanitisation on Artisan commands.
- PHP 8.2–8.4 and Laravel 11–12 support.
