# Entitlements for Laravel

[![Tests](https://github.com/gwhitdev/laravel-entitlements/actions/workflows/tests.yml/badge.svg)](https://github.com/gwhitdev/laravel-entitlements/actions/workflows/tests.yml)
[![Latest Version on Packagist](https://img.shields.io/packagist/v/gwhitdev/entitlements-for-laravel.svg)](https://packagist.org/packages/gwhitdev/entitlements-for-laravel)
[![PHP Version](https://img.shields.io/packagist/php-v/gwhitdev/entitlements-for-laravel.svg)](https://packagist.org/packages/gwhitdev/entitlements-for-laravel)
[![License](https://img.shields.io/github/license/gwhitdev/laravel-entitlements)](LICENSE)

> Plan-based feature **entitlements** for Laravel — the layer between Cashier (*who pays*) and your app (*what paying unlocks*). Facade: **Tessera**. Part of the [Keel](https://github.com/gwhitdev) line.

Cashier gives you subscriptions. Pennant gives you feature flags. Neither answers the question every SaaS actually asks: **"does this user's plan include this feature?"** That's an *entitlement*, and this package is the engine for it — a memoized resolution cascade over plan mappings, per-user grants, and membership state, with the seams to swap billing provider and feature catalog without rewrites.

```php
$user->hasFeature('advanced_reporting');           // trait on your User model
Tessera::has($user, 'advanced_reporting');          // or the facade
Route::get('/reports', ...)->middleware('feature:advanced_reporting');
```

## Status

**Alpha — v0.3.0.** The core engine, DX layer, declarative dependencies, security hardening, and Pennant bridge are all complete. Laravel 11–13 and PHP 8.2–8.4 are tested in CI. The package is usable and under real-world validation — install it, kick the tyres, and open issues. Production-stability guarantee comes at v1.0.

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13
- [Cashier](https://laravel.com/docs/billing) (optional) — the default `PlanResolver` reads Stripe prices through it; the seam supports other billers.

## Installation

```bash
composer require gwhitdev/entitlements-for-laravel
```

Publish the config and migrations, then migrate:

```bash
php artisan entitlements:install   # publishes config + migrations, prints next steps
php artisan migrate
```

Add the trait to your `User` model:

```php
use Entitlements\Concerns\HasFeatures;

class User extends Authenticatable
{
    use HasFeatures;
}
```

## Quick Start

1. **Define your features.** Scaffold a case onto your feature enum:

   ```bash
   php artisan entitlements:make AdvancedReporting
   ```

2. **Map plans to features.** With the `config` plan store, declare the mapping as code in `config/entitlements.php` (the default `database` store keeps it runtime-editable instead):

   ```php
   'plan_store' => 'config',
   'plans' => [
       'price_pro_monthly' => ['advanced_reporting', 'team_seats'],
   ],
   ```

3. **Check entitlement** anywhere — trait, facade, middleware, or Blade:

   ```php
   if ($user->hasFeature('advanced_reporting')) {
       // ...
   }
   ```

The gate resolves a cascade — admin override → per-user grant → plan mapping (via the active subscription's price) — and memoizes the result per request.

## Usage

### On the User model (`HasFeatures` trait)

```php
$user->hasFeature('advanced_reporting');   // bool — is the user entitled?
$user->features();                         // string[] — every key the user is entitled to
$user->grantFeature('advanced_reporting'); // direct grant, an override above their plan
```

### Via the facade

```php
use Entitlements\Facades\Tessera;

Tessera::has($user, 'advanced_reporting');  // bool
Tessera::entitlements($user);               // string[]
```

### Route middleware

The `feature:` alias aborts with `403` unless the authenticated user is entitled:

```php
Route::get('/reports', ReportController::class)
    ->middleware('feature:advanced_reporting');
```

### Blade directive

```blade
@feature('advanced_reporting')
    <a href="/reports">Advanced reporting</a>
@endfeature
```

### Pennant bridge (optional)

If your codebase already uses Laravel Pennant, you can expose all entitlements as Pennant features so existing `Feature::` check sites keep working:

```php
// In AppServiceProvider::boot()
use Entitlements\Bridge\PennantBridge;

PennantBridge::register();
```

After calling this, Pennant's standard API resolves through the entitlement cascade:

```php
use Laravel\Pennant\Feature;

Feature::for($user)->active('advanced_reporting'); // delegates to Tessera
```

Pennant's own storage and caching are bypassed — resolution always goes through the cascade, so grants and plan changes are reflected immediately.

### Artisan commands

| Command | What it does |
| --- | --- |
| `entitlements:install` | Publishes config + migrations and prints next steps. |
| `entitlements:make <Name>` | Adds a case to your feature enum. |
| `entitlements:lint` | Checks that every plan → feature mapping satisfies its declared prerequisites. |

## Configuration

Every moving part is a swappable seam, bound from `config/entitlements.php`:

```php
return [
    // The public resolution gate (implements Entitlements\Contracts\FeatureGate).
    'gate' => \Entitlements\Gate\CascadingFeatureGate::class,

    // The billing seam. Default reads Cashier's Stripe price; swap for
    // Paddle / Lemon Squeezy by pointing this at your own PlanResolver.
    'resolver' => \Entitlements\Resolvers\StripePlanResolver::class,

    // The catalog seam. Default is enum-backed; ConfigFeatureCatalog is also available.
    'catalog' => \Entitlements\Catalog\EnumFeatureCatalog::class,
    'enum' => \App\Enums\Feature::class,

    // Where plan → feature mappings live: 'database' (runtime-editable) or 'config'.
    'plan_store' => 'database',
    'plans' => [],

    // Off by default (fail-closed). When enabled, an admin is entitled to every feature; prefer
    // defining isEntitlementAdmin() on your User model over relying on a raw is_admin column.
    'admin_override' => false,
];
```

`FeatureGate` is the only public surface — the cascade and memoization live behind it, which keeps the package headless and the seams independently swappable.

## What this package is — and isn't

Entitlements solves one problem precisely: **given a user and a feature key, is that user entitled to it?** It answers that by reading your plan → feature mapping, per-user grants, and Cashier's active subscription state — then getting out of the way.

It is deliberately **not** a billing engine and **not** a usage tracker:

| Concern | Owner |
| --- | --- |
| *Does this plan include this feature?* (access gating) | **Entitlements** ✅ |
| Per-user grants / overrides above a plan | **Entitlements** ✅ |
| Consumable quotas — counting, decrementing, resetting usage | [Stripe Meters](https://docs.stripe.com/billing/subscriptions/usage-based) or a dedicated quota package |
| Overage billing — charging when an allocation is exceeded | Cashier / your billing provider |
| Subscription lifecycle — trials, grace periods, renewal webhooks | Cashier (Entitlements reads `isActive()` through the `PlanResolver` seam) |

If you need quota tracking on top of access gating, the roadmap includes a **consumable feature type** (allocations on `plan_features`, a usage table, `consume()`/`canConsume()`/`remainingUsage()` on the trait) as a possible future stage — but it is not built yet. The access-gating core is stable and useful without it.

## Design at a glance

- **Free MIT core** (this package) + a paid admin UI (drag-drop plan ↔ feature mapping) sold separately.
- **Seams, defaulted:** `PlanResolver` (Cashier by default, billing-agnostic underneath), `FeatureCatalog` (enum by default; config/DB drivers), `FeatureGate` (the only public surface — keeps it headless).
- **Pennant bridge** (optional): expose entitlements as Pennant features for a familiar check API.
- **Built for artisans *and* agents:** ships with [`AGENTS.md`](AGENTS.md) so an AI assistant can gate a feature correctly in one prompt.

## Testing

```bash
composer test
```

The suite runs against PHP 8.2–8.4 and Laravel 11–12 in CI. A dependency audit job runs alongside it on every push.

## Security

Please email [garethwhitleychard@gmail.com](mailto:garethwhitleychard@gmail.com) to report vulnerabilities rather than opening a public issue. You'll receive a response within 72 hours.

## Changelog

See [CHANGELOG.md](CHANGELOG.md) for release history.

## License

MIT.
