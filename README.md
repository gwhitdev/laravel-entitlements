# Entitlements for Laravel

> Plan-based feature **entitlements** for Laravel — the layer between Cashier (*who pays*) and your app (*what paying unlocks*). Facade: **Tessera**. Part of the [Keel](https://github.com/gwhitdev) line.

Cashier gives you subscriptions. Pennant gives you feature flags. Neither answers the question every SaaS actually asks: **"does this user's plan include this feature?"** That's an *entitlement*, and this package is the engine for it — a memoized resolution cascade over plan mappings, per-user grants, and membership state, with the seams to swap billing provider and feature catalog without rewrites.

```php
$user->hasFeature('advanced_reporting');          // trait on your User model
Tessera::has($user, 'advanced_reporting');         // or the facade
Route::get('/reports', ...)->middleware('feature:advanced_reporting');
```

## Status

🚧 **In development.** The contracts, schema, and acceptance spec are pinned — see [`SPEC.md`](SPEC.md). Stage 1 (the free core engine) is in progress.

## Design at a glance

- **Free MIT core** (this package) + a paid admin UI (drag-drop plan↔feature mapping) sold separately.
- **Seams, defaulted:** `PlanResolver` (Cashier by default, billing-agnostic underneath), `FeatureCatalog` (enum by default; config/DB drivers), `FeatureGate` (the only public surface — keeps it headless).
- **Pennant bridge** (optional): expose entitlements as Pennant features for a familiar check API.
- **Built for artisans *and* agents:** ships with agent instructions so an AI assistant can gate a feature correctly in one prompt.

## License

MIT.
