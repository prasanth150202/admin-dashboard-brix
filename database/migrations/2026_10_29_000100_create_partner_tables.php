<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_apps', function (Blueprint $table) {
            $table->id();
            // Numeric Partner app ID, i.e. the 1234 in gid://partners/App/1234.
            $table->string('partner_app_id')->unique();
            $table->string('name');
            $table->string('api_key')->nullable();
            $table->timestamp('events_synced_until')->nullable();
            $table->timestamp('transactions_synced_until')->nullable();
            $table->timestamps();
        });

        Schema::create('shops', function (Blueprint $table) {
            $table->id();
            $table->string('myshopify_domain')->unique();
            $table->string('partner_shop_id')->nullable();
            $table->string('name')->nullable();
            // Derived by ShopClassifier: client | test | unknown.
            $table->string('kind')->default('unknown')->index();
            // Set by hand in the store page; always wins over the derived kind.
            $table->string('kind_override')->nullable();
            $table->boolean('is_paid')->default(false)->index();
            $table->decimal('lifetime_net', 14, 2)->default(0);
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_charge_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('app_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_app_id')->constrained('partner_apps')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            // AppEventTypes enum value, e.g. RELATIONSHIP_INSTALLED.
            $table->string('type')->index();
            $table->timestamp('occurred_at')->index();
            $table->string('charge_id')->nullable();
            $table->string('charge_name')->nullable();
            $table->decimal('charge_amount', 14, 2)->nullable();
            $table->string('charge_currency', 3)->nullable();
            $table->boolean('charge_test')->nullable();
            $table->timestamp('billing_on')->nullable();
            $table->string('reason')->nullable();
            $table->text('description')->nullable();
            // AppEvent has no ID in the Partner API, so rows are keyed on a
            // hash of what makes an event unique. See EventSync::dedupeKey().
            $table->string('dedupe_key', 64)->unique();
            $table->json('payload')->nullable();
            $table->timestamps();
        });

        Schema::create('app_installs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_app_id')->constrained('partner_apps')->cascadeOnDelete();
            $table->foreignId('shop_id')->constrained('shops')->cascadeOnDelete();
            // installed | uninstalled | deactivated
            $table->string('status')->index();
            $table->timestamp('first_installed_at')->nullable();
            $table->timestamp('last_installed_at')->nullable();
            $table->timestamp('uninstalled_at')->nullable();
            $table->unsignedInteger('install_count')->default(0);
            $table->string('uninstall_reason')->nullable();
            $table->string('plan_name')->nullable();
            $table->decimal('plan_amount', 14, 2)->nullable();
            $table->string('plan_currency', 3)->nullable();
            $table->boolean('plan_test')->nullable();
            // active | cancelled | declined | expired | frozen
            $table->string('plan_status')->nullable();
            $table->timestamp('plan_billing_on')->nullable();
            $table->decimal('lifetime_net', 14, 2)->default(0);
            $table->timestamps();
            $table->unique(['partner_app_id', 'shop_id']);
        });

        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('partner_transaction_id')->unique();
            $table->foreignId('partner_app_id')->nullable()->constrained('partner_apps')->cascadeOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained('shops')->nullOnDelete();
            // GraphQL __typename, e.g. AppSubscriptionSale, AppSaleCredit.
            $table->string('type')->index();
            $table->timestamp('occurred_at')->index();
            $table->decimal('gross_amount', 14, 2)->default(0);
            $table->decimal('shopify_fee', 14, 2)->default(0);
            $table->decimal('net_amount', 14, 2)->default(0);
            $table->string('currency', 3)->nullable();
            $table->string('charge_id')->nullable();
            $table->string('billing_interval')->nullable();
            $table->date('payout_period_start')->index();
            $table->date('payout_period_end');
            $table->timestamps();
        });

        Schema::create('sync_runs', function (Blueprint $table) {
            $table->id();
            // schedule | manual | cli
            $table->string('trigger');
            // incremental | full
            $table->string('mode');
            // running | ok | failed
            $table->string('status')->default('running');
            $table->unsignedInteger('events_fetched')->default(0);
            $table->unsignedInteger('transactions_fetched')->default(0);
            $table->text('message')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sync_runs');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('app_installs');
        Schema::dropIfExists('app_events');
        Schema::dropIfExists('shops');
        Schema::dropIfExists('partner_apps');
    }
};
