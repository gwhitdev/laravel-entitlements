# Stage 3 Implementation Prompt — entitlements-for-laravel (declarative dependencies)

You are implementing **Stage 3** of the `gwhitdev/entitlements-for-laravel` package. Repo root = this directory. Strict TDD: extend Pest tests, run `vendor/bin/pest`, never finish red.

## The locked decision you are implementing (read SPEC.md "Stage 3" section)
**Dependencies are DECLARATIVE metadata, NOT runtime gating.** `hasFeature()` does NOT consult dependencies — no auto-grant, no silent denial. Bundling is the plan→feature mapping's job. Dependencies exist as catalog metadata that powers *soft guidance* (warnings), never enforcement.

## ABSOLUTE RULES (a prior agent violated these)
1. **DO NOT modify the resolution engine.** `src/Gate/CascadingFeatureGate.php` must stay exactly as-is. Dependencies must NOT affect `has()` / `entitlements()`. If you change the cascade, you have failed this stage.
2. **DO NOT modify `src/Contracts/`** — the interfaces are frozen. `FeatureCatalog::dependenciesFor()` already exists; use it. No contract changes are needed.
3. **Keep all existing 24 tests green.** Run `vendor/bin/pest` before and after every change.
4. **Work on a branch:** `git checkout -b stage-3` from `main`. Commit there. Do NOT push to any remote, do NOT merge to main, do NOT force-push. When done, you may open a PR with `gh pr create --base main` but do not merge it.
5. **Stage 3 ONLY** — do not build the admin UI, grant-through/implied-grant, or anything labelled Stage 4+. No `--no-audit` or audit-suppression in CI, ever.
6. Package conventions: namespace `Entitlements\` (PSR-4 → src/), plain `<?php`, 4-space indent, typed signatures, PHPDoc on public methods. It's a package (no `php artisan`) — test via Orchestra Testbench (`$this->artisan(...)`), base class `Entitlements\Tests\TestCase`. No new runtime dependencies.
7. Git commits: clear messages, **no `Co-Authored-By` trailer**.

## Deliverables

### 1. `ConfigFeatureCatalog` — `src/Catalog/ConfigFeatureCatalog.php`
Config-driven catalog driver reading `config('entitlements.features')`. **It MUST normalise every entry to the full contract shape** `{key, name, description, group, dependencies}` (the reverted version omitted `name`/`description` — that was a contract-shape bug; do not repeat it). Defaults for a config entry: `name` = `Str::headline($key)`, `description` = `null`, `group` = `'Other'`, `dependencies` = `[]`. Implement all four methods (`all`, `has`, `dependenciesFor`, `groupFor`) consistently with `EnumFeatureCatalog`.
- Add a `features` key to `config/entitlements.php` (default `[]`) with a comment showing the entry shape: `['key' => 'advanced_reporting', 'group' => 'Reporting', 'dependencies' => ['expense_tracking']]`.
- **Test** `tests/Feature/ConfigFeatureCatalogTest.php`: set `config('entitlements.features')`, assert `all()` returns fully-normalised entries (every entry has all 5 keys, defaults applied), and that `has`/`dependenciesFor`/`groupFor` behave; empty config → `all()` is `[]`.

### 2. `PrerequisiteChecker` — `src/Support/PrerequisiteChecker.php`
The engine-side support the future admin UI / lint will use to surface unmet declared prerequisites. **Read-only analysis; it never changes entitlements.**
- `forPlan(string $planIdentifier): array` — returns `['feature_key' => ['missing_prereq_key', ...], ...]` for features mapped to that plan whose declared `dependenciesFor()` are NOT also mapped to the same plan. Features with all prerequisites present are omitted.
- `all(): array` — `['plan_identifier' => (forPlan result), ...]` across every known plan, omitting plans with no issues.
- Resolve a plan's mapped feature keys honouring `config('entitlements.plan_store')`: `'database'` → `PlanFeature::where('plan_identifier', …)->pluck('feature_key')`; `'config'` → `config("entitlements.plans.{$planIdentifier}", [])`. Enumerate all plans: database → `PlanFeature::distinct()->pluck('plan_identifier')`; config → `array_keys(config('entitlements.plans', []))`.
- Read declared dependencies via `app(FeatureCatalog::class)->dependenciesFor($key)`.
- **Test** `tests/Feature/PrerequisiteCheckerTest.php`: use `ConfigFeatureCatalog` (set `entitlements.catalog` + `entitlements.features` with a dependency), map a plan to the dependent feature but NOT its prerequisite (use `PlanFeature::grant`), assert `forPlan` reports the missing prerequisite; then map the prerequisite too and assert it's clean. Cover both `plan_store` modes if practical.

### 3. `entitlements:lint` command — `src/Console/LintCommand.php`
- `protected $signature = 'entitlements:lint';`
- Uses `PrerequisiteChecker::all()`. For each plan with issues, print a warning line per feature: `Plan '{plan}': feature '{feature}' is missing prerequisite(s): {list}`.
- Return `self::FAILURE` if any unmet prerequisites were found, else `self::SUCCESS` with an "all plans satisfy their declared prerequisites" message. Register in the provider's `runningInConsole()` command list.
- **Test** `tests/Feature/LintCommandTest.php`: a plan missing a prerequisite → `assertFailed()` + `expectsOutputToContain('missing prerequisite')`; a satisfied setup → `assertSuccessful()`.

## Definition of done
- [ ] `ConfigFeatureCatalog` (full-shape normalised), `PrerequisiteChecker`, `entitlements:lint` implemented; lint registered in the provider.
- [ ] New tests for each, plus the existing 24 — **all green**.
- [ ] `CascadingFeatureGate` and `src/Contracts/` UNCHANGED (verify with `git diff main -- src/Gate src/Contracts` showing no changes).
- [ ] No new runtime deps; no CI audit suppression.
- [ ] Append a short "Stage 3 (built)" note to SPEC.md listing what shipped.

When done, report: files created/modified, the final `vendor/bin/pest` summary line, and confirm `git diff main -- src/Gate src/Contracts` is empty.
