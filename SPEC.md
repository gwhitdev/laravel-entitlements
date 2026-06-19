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
- `Resolvers\StripePlanResolver` implements `PlanResolver` — reads the user's Stripe price + active state via Cashier. Named for the provider (Stripe), not the package (Cashier), to match future PaddlePlanResolver / LemonSqueezyPlanResolver.
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

- [x] `CascadingFeatureGate` + `StripePlanResolver` + `EnumFeatureCatalog` + `HasFeatures` trait.
- [x] All `ResolutionCascadeSpecTest` cases un-skipped and green.
- [x] Cashier-optional (bound via `class_exists`); package still boots without it.
- [x] CI matrix green; Pint clean.

## Stage 2 (DX) — batteries-included layer

The commands, middleware, Blade directive, and frontend stub that make Entitlements feel native in a Laravel app.

### Commands

- **`entitlements:install`** — Publishes config and migrations, then prints next steps (add `HasFeatures` trait, run `migrate`, run `entitlements:make`).
- **`entitlements:make {name}`** — Appends a case to the consumer's feature enum. Creates the enum file if it doesn't exist. Idempotent (skips duplicate values).

### HTTP Middleware

- **`feature` middleware alias** — `Route::middleware('feature:advanced_reporting')` aborts 403 unless the authenticated user is entitled to the given feature key. Guests always 403.

### Blade

- **`@feature('key')` / `@endfeature`** — Conditional Blade directive wrapping `$user->hasFeature($key)`. Shows content for entitled users; hides it for guests and non-entitled users.

### Frontend exposure

- **`Entitlements::forUser($user)`** — Returns the array of feature keys for sharing as an Inertia prop.
- **`resources/js/useFeature.ts`** — Publishable stub reading `usePage().props.features` and exposing a `(key: string) => boolean` check. Published via `php artisan vendor:publish --tag=entitlements-js`.

## Stage 3 (Dependencies) — declarative metadata, bundle via plans

**Decision (locked): dependencies are DECLARATIVE metadata, NOT runtime gating.** `hasFeature()` stays exactly as the Stage 1 cascade — dependencies do not auto-grant and do not silently deny. Bundling features together is the job of the **plan → feature mapping**, not feature-to-feature edges.

**Why.** Both implied-grant and strict-prerequisite make `hasFeature` return something the operator did not literally configure (invent or revoke an entitlement) — dangerous for a billing primitive. Plans already deliver coherence ("Pro lists `year_end_pack` AND `expense_tracking`"). A feature-to-feature grant-through would be a second bundling mechanism at the wrong layer, and reads as non-idiomatic — Stripe Entitlements and RBAC bundle at the product/role level, not via feature dependencies.

### What dependencies DO
- **Catalog metadata** — `FeatureCatalog::dependenciesFor($key)` returns declared prerequisites.
- **Soft admin guidance** — when an operator maps a feature to a plan missing its declared prerequisites, the admin UI warns ("`year_end_pack` needs `expense_tracking` — add it?"). It never blocks and never rewrites entitlements.
- **Ordering / grouping / dependency visualizer** in the admin UI.
- Optional `entitlements:lint` / config check flagging plans with unmet declared prerequisites.

### Deliberately NOT in the engine
- `hasFeature()` does not consult dependencies. Coherence comes from the operator listing the bundle in the plan, helped by the warnings above — i.e. push the complexity to config-time where a human sees it, not runtime where it surprises.

### Future opt-in (documented only, OFF by default — do not lead with this)
- A `grant-through` mode for true composite features (à la Keycloak composite roles) MAY be added later as opt-in config. Secure-by-default rule if ever built: nothing auto-grants unless explicitly marked, plus a leak detector cross-referencing granted targets against standalone-sold features.

### Catalog driver to reintroduce (from the reverted Stage 3, corrected)
- `ConfigFeatureCatalog` (reads `config('entitlements.features')`) — but it MUST normalize each entry to the full `{key, name, description, group, dependencies}` shape to match `EnumFeatureCatalog` (the reverted version omitted `name`/`description`, breaking the contract shape).

## Stage 3 (built)

Shipped on `stage-3` branch. Three deliverables implemented, all 41 tests green (24 pre-existing + 17 new).

- **`src/Catalog/ConfigFeatureCatalog.php`** — Config-driven catalog driver reading `config('entitlements.features')`. Normalises every entry to the full five-key contract shape `{key, name, description, group, dependencies}` with sensible defaults. Consistent in style with `EnumFeatureCatalog`.
- **`config/entitlements.php`** — Added `features` key (default `[]`) with a comment showing the entry shape.
- **`src/Support/PrerequisiteChecker.php`** — Read-only analysis class. `forPlan()` returns features mapped to a plan whose declared dependencies are not also mapped. `all()` returns issues across every known plan. Honours both `plan_store` modes (`database` / `config`). Never changes entitlements.
- **`src/Console/LintCommand.php`** — `entitlements:lint` command; uses `PrerequisiteChecker::all()`; prints a warning per unmet prerequisite; exits `FAILURE` if any issues found, `SUCCESS` with a clean message otherwise. Registered in `EntitlementsServiceProvider`.
- **Tests**: `tests/Feature/ConfigFeatureCatalogTest.php`, `tests/Feature/PrerequisiteCheckerTest.php`, `tests/Feature/LintCommandTest.php`.
- `src/Gate/CascadingFeatureGate.php` and `src/Contracts/` are byte-for-byte unchanged — `git diff main -- src/Gate src/Contracts` is empty.
