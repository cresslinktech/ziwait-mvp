<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('plan_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('interval', 10); // month|year
            $table->char('currency', 3);
            $table->unsignedBigInteger('amount_minor');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['plan_id', 'interval', 'currency']);
        });

        Schema::create('plan_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('key', 60); // e.g. max_branches ("unlimited" or a number)
            $table->string('value', 60);
            $table->timestamps();

            $table->unique(['plan_id', 'key']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained();
            $table->foreignId('plan_price_id')->nullable()->constrained();
            $table->string('status', 20); // trialing|active|past_due|expired|cancelled
            $table->timestamp('current_period_start')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('renewal_reminded_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'id']);
            $table->index(['status', 'current_period_end']);
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('plan_price_id')->constrained(); // what this payment buys
            $table->string('gateway', 30);
            $table->string('reference', 64)->unique(); // ours, sent to the gateway
            $table->string('gateway_transaction_id', 64)->nullable()->unique();
            $table->unsignedBigInteger('amount_minor'); // expected amount, set by the server
            $table->char('currency', 3);
            $table->string('status', 20)->default('pending'); // pending|successful|failed|refunded|review
            $table->string('failure_reason')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('verified_at')->nullable();
            $table->timestamps();

            $table->index(['business_id', 'created_at']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('payment_events', function (Blueprint $table) {
            $table->id();
            $table->string('gateway', 30);
            $table->string('event_type', 60);
            $table->string('dedupe_key', 190);
            $table->string('reference', 64)->nullable()->index();
            $table->json('payload');
            $table->string('outcome', 30)->nullable();
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->unique(['gateway', 'dedupe_key']);
        });

        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payment_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('number', 40)->unique();
            $table->unsignedBigInteger('amount_minor');
            $table->char('currency', 3);
            $table->string('description');
            $table->timestamp('issued_at');
            $table->timestamps();
        });

        Schema::create('manual_payment_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 40); // amount_mismatch|currency_mismatch|reference_mismatch|duplicate_transaction|refund_event|other
            $table->text('details')->nullable();
            $table->string('status', 12)->default('open'); // open|approved|rejected
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('resolution_note')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['manual_payment_reviews', 'invoices', 'payment_events', 'payments', 'subscriptions', 'plan_entitlements', 'plan_prices', 'plans'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
