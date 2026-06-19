<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Optional catalog store — used only by the database catalog driver. The enum/config
        // drivers ignore it. Referenced elsewhere by `key` (string), never by FK, so the
        // mapping tables work regardless of which catalog driver is active.
        Schema::create('features', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('group')->default('Other');
            $table->timestamps();
        });

        // Plan -> feature mapping. `plan_identifier` is an OPAQUE string (Stripe price id today,
        // a Paddle plan id tomorrow) — deliberately not named stripe_price_id, so going
        // billing-agnostic needs no migration.
        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->string('plan_identifier')->index();
            $table->string('feature_key')->index();
            $table->timestamps();

            $table->unique(['plan_identifier', 'feature_key']);
        });

        // Explicit per-user grant (an override above plan-derived entitlements — trials, comps,
        // grandfathering). Keyed by feature_key string for the same driver-agnostic reason.
        Schema::create('user_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->index();
            $table->string('feature_key')->index();
            $table->timestamps();

            $table->unique(['user_id', 'feature_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_features');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('features');
    }
};
