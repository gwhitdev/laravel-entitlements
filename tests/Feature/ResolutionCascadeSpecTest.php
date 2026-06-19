<?php

// STAGE 1 TARGET (executable spec). These document the resolution cascade the free core must
// implement. They are skipped so CI is green at Stage 0; Stage 1 removes the ->skip() and makes
// each pass against the default CascadingFeatureGate + CashierPlanResolver + EnumFeatureCatalog.
//
// Cascade order (see SPEC.md):
//   1. admin override        — is_admin user is entitled to everything
//   2. explicit per-user grant — a user_features row grants regardless of plan
//   3. membership active gate  — if not active, no plan-derived entitlements
//   4. plan-derived grant      — plan_features mapping for the user's plan_identifier
// Resolution is memoized per request. Feature dependencies are Stage 3, not asserted here.

$stage1 = 'Stage 1 target: implement the resolution cascade';

it('grants every feature to an admin (override at the top of the cascade)', function () {
    // $admin (is_admin = true) → Tessera::has($admin, 'anything') === true
})->skip($stage1);

it('grants a feature via an explicit per-user grant regardless of plan', function () {
    // user_features row for 'advanced_reporting' → has() true even with no active plan
})->skip($stage1);

it('grants plan-mapped features to an active subscriber', function () {
    // plan_features maps the user's plan_identifier → 'advanced_reporting'; membership active → true
})->skip($stage1);

it('denies plan-mapped features when membership is inactive', function () {
    // same mapping, but PlanResolver::isActive() === false → has() false
})->skip($stage1);

it('denies features the catalog/plan does not include', function () {
    // unmapped key, no grant, not admin → has() false
})->skip($stage1);

it('memoizes resolution within a request (no repeated queries)', function () {
    // repeated has()/entitlements() calls hit the DB once
})->skip($stage1);
