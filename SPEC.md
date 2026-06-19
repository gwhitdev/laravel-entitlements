# Stage 1 Spec — Free Core Engine

The contracts, schema, and acceptance criteria the free core must satisfy. The interfaces in
`src/Contracts/` are **pinned** — implementations land behind them; consumers and the (paid) UI
only ever touch `FeatureGate` (via the `Tessera` facade / `HasFeatures` trait).

## Pinned contracts (`src/Contracts/`)

- **`PlanResolver`** — `planIdentifier($user): ?string`, `isActive($user): bool`. The billing seam.
- **`FeatureCatalog`** — `all()`, `has($key)`, `dependenciesFor($key)`, `groupFor($key)`. The catalog seam.
- **`FeatureGate`** — `has($user, $key): bool`, `entitlements($user): array`. The public surface.

## Default implementations (Stage 1 builds these)

- `Gate\CascadingFeatureGate` implements `FeatureGate` — the cascade below, memoized per request.
- `Resolvers\CashierPlanResolver` implements `PlanResolver` — reads the user's Stripe price + active state. Bound only if `laravel/cashier` is present (it's a suggest, not a hard require — the billing-agnostic seam).
- `Catalog\EnumFeatureCatalog` implements `FeatureCatalog` — reads the consumer enum at `config('entitlements.enum')`.
- `Concerns\HasFeatures` trait for the User model — `hasFeature($key)` delegates to `FeatureGate`.

## Resolution cascade (the heart of the core)

In order; first match wins:
1. **Admin override** — if `config('entitlements.admin_override')` and the user is `is_admin`, granted.
2. **Explicit per-user grant** — a `user_features` row for the key grants regardless of plan.
3. **Membership gate** — if `PlanResolver::isActive($user)` is false, no plan-derived entitlements.
4. **Plan-derived grant** — `plan_features` mapping for the user's `plan_identifier` includes the key.

Resolution is **memoized per request** (mirror the proven 3-field memo + reset pattern). Feature
**dependencies** are Stage 3 — not part of Stage 1.

## Schema (shipped, `database/migrations/`)

- `features` (optional; database catalog driver only) — referenced by `key` string, never FK.
- `plan_features` — `plan_identifier` (OPAQUE — Stripe today, Paddle tomorrow; never `stripe_price_id`) + `feature_key`, unique together.
- `user_features` — `user_id` + `feature_key`, unique together.

All cross-references use `feature_key` strings so the mapping works regardless of catalog driver.

## Acceptance tests

- `tests/Feature/SpecBootTest.php` — **passes now** (Stage 0 green gate): boots, config defaults, schema migrates, opaque column name.
- `tests/Feature/ResolutionCascadeSpecTest.php` — **skipped Stage 1 target**: remove `->skip()` and implement the cascade until green.

## Definition of done (Stage 1)

- [ ] `CascadingFeatureGate` + `CashierPlanResolver` + `EnumFeatureCatalog` + `HasFeatures` trait.
- [ ] All `ResolutionCascadeSpecTest` cases un-skipped and green.
- [ ] Cashier-optional (bound via `class_exists`); package still boots without it.
- [ ] CI matrix green; Pint clean.
