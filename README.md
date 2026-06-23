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

## What this package is — and isn't

Entitlements solves one problem precisely: **given a user and a feature key, is that user entitled to it?** It answers that by reading your plan→feature mapping, per-user grants, and Cashier's active subscription state — then getting out of the way.

It is deliberately **not** a billing engine and **not** a usage tracker. Specifically out of scope:

- **Consumable quotas** ("10 API calls per month") — tracking, decrementing, and resetting usage counters. Use [Stripe Meters](https://docs.stripe.com/billing/subscriptions/usage-based) or a dedicated quota package for this; Entitlements can gate access to a metered feature but does not manage the meter itself.
- **Overage billing** — charging users when they exceed an allocation. That belongs in Cashier/your billing provider.
- **Subscription lifecycle** — trial periods, grace periods, renewal webhooks. Cashier owns this; Entitlements reads `isActive()` through the `PlanResolver` seam and trusts what it gets back.

If you need quota tracking on top of access gating, the roadmap includes a **consumable feature type** (allocations on `plan_features`, a usage table, `consume()`/`canConsume()`/`remainingUsage()` on the trait) as a possible future stage — but it is not built yet. The access-gating core is stable and useful without it.

## License

MIT.
