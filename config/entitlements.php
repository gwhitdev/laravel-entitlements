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
    'resolver' => \Entitlements\Resolvers\StripePlanResolver::class,

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
     | The Cashier subscription name passed to subscription() and subscribed().
     | Null resolves to Cashier's default ('default'). Set this if your app uses
     | a named subscription (e.g. 'main') rather than the default one.
     */
    'subscription_name' => null,

    /*
     | Admin override sits at the very top of the cascade: when enabled, an admin user is entitled
     | to EVERY feature. It is OFF by default (fail-closed) so that an accidentally-set or
     | mass-assignable `is_admin` attribute can never become a blanket entitlement bypass.
     | Opt in explicitly, and prefer defining isEntitlementAdmin() on your User model over relying
     | on a raw `is_admin` column.
     */
    'admin_override' => false,

    /*
     | Feature definitions used by ConfigFeatureCatalog (when catalog driver is set to it).
     | Each entry shape: ['key' => 'advanced_reporting', 'group' => 'Reporting', 'dependencies' => ['expense_tracking']]
     | Optional keys: 'name' (defaults to Str::headline($key)), 'description' (defaults to null).
     */
    'features' => [],
];
