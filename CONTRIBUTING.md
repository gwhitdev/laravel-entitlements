# Contributing

Contributions are welcome. Please open an issue before starting work on a non-trivial change — this avoids duplicated effort and keeps PRs focused.

## Setup

```bash
git clone git@github.com:gwhitdev/laravel-entitlements.git
cd laravel-entitlements
composer install
composer test
```

## Workflow

1. Fork the repo and create a branch from `main`.
2. Make your changes.
3. Run `composer test` — all 52 tests must pass.
4. Open a pull request against `main`.

## Guidelines

**Seams, not rewrites.** The three swappable seams (`FeatureGate`, `PlanResolver`, `FeatureCatalog`) are the extension points. New billing providers or catalog drivers should implement the relevant contract rather than modifying the defaults.

**`FeatureGate` is the only public surface.** Don't add public methods to models, the gate implementation, or the service provider that bypass the contract.

**Models are fully guarded.** `UserFeature` and `PlanFeature` use `$guarded = ['*']` by design. Any new write paths must go through a controlled static method, not mass assignment.

**Tests go in `tests/Feature/`.** The suite tests behaviour through the public trait and facade — there are no unit tests by design. Use `FakePlanResolver` from `tests/Fixtures/` to isolate billing from cascade behaviour.

**Run the linter if you touch plan mappings:**

```bash
php artisan entitlements:lint
```

## Reporting vulnerabilities

Please see [SECURITY.md](SECURITY.md).
