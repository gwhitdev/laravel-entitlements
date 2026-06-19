<?php

return [
    /*
     | The public resolution gate (implements Entitlements\Contracts\FeatureGate).
     | Wired in Stage 1.
     */
    'gate' => \Entitlements\Gate\CascadingFeatureGate::class,

    /*
     | The billing seam (implements Entitlements\Contracts\PlanResolver).
     | Default reads Cashier's Stripe price; swap for Paddle/Lemon Squeezy without other changes.
     */
    'resolver' => \Entitlements\Resolvers\CashierPlanResolver::class,

    /*
     | The catalog seam (implements Entitlements\Contracts\FeatureCatalog).
     | Default is enum-backed. Alternatives: ConfigFeatureCatalog, DatabaseFeatureCatalog.
     */
    'catalog' => \Entitlements\Catalog\EnumFeatureCatalog::class,

    /*
     | The consumer's feature enum (string-backed), used by the enum catalog driver.
     */
    'enum' => \App\Enums\Feature::class,

    /*
     | Where plan -> feature mappings live. 'database' (default; runtime-editable, the paid UI's
     | surface) or 'config' (version-controlled config-as-code; pairs with entitlements:export).
     */
    'plan_store' => 'database',

    /*
     | Plan -> feature map used only when plan_store is 'config'.
     | [ 'price_pro_monthly' => ['advanced_reporting', 'team_seats'] ]
     */
    'plans' => [],

    /*
     | When true, users flagged is_admin are entitled to every feature (top of the cascade).
     */
    'admin_override' => true,
];
