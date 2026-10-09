<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('menu_categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'is_active', 'sort_order']);
        });

        Schema::create('menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_category_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('image_path')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['business_id', 'menu_category_id', 'is_active']);
        });

        // Branch-specific price and availability. An item is orderable at a branch only if a row exists here.
        Schema::create('branch_menu_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('price_minor');
            $table->boolean('is_available')->default(true);
            $table->boolean('is_visible')->default(true);
            $table->timestamps();

            $table->unique(['branch_id', 'menu_item_id']);
            $table->index(['branch_id', 'is_visible', 'is_available']);
        });

        Schema::create('qr_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('token', 64)->unique();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'revoked_at']);
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->string('reference', 20)->unique();
            $table->string('public_token', 64)->unique(); // unguessable confirmation link
            $table->string('status', 20)->default('submitted');
            $table->string('customer_name');
            $table->string('customer_phone', 32);
            $table->string('customer_email')->nullable();
            $table->text('notes')->nullable();
            $table->char('currency', 3);
            $table->unsignedBigInteger('total_minor');
            $table->timestamp('placed_at');
            $table->timestamp('anonymised_at')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'status', 'placed_at']);
            $table->index(['business_id', 'placed_at']);
        });

        // Immutable snapshots: never join back to menu_items for display.
        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->unsignedBigInteger('unit_price_minor');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total_minor');
            $table->timestamps();

            $table->index('order_id');
        });

        Schema::create('order_status_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'created_at']);
        });

        Schema::create('notification_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('channel', 40);
            $table->string('status', 10); // sent|failed
            $table->text('error')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['order_id', 'status']);
        });
    }

    public function down(): void
    {
        foreach (['notification_logs', 'order_status_history', 'order_items', 'orders', 'qr_links', 'branch_menu_items', 'menu_items', 'menu_categories'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
