<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Orders, their lines and the history of their status changes.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('token')->unique();
            $table->string('status', 20)->default('pending')->index();
            $table->string('currency', 3);
            $table->string('customer_name');
            $table->string('customer_email');
            $table->string('customer_phone', 40)->nullable();
            $table->string('address_line1');
            $table->string('address_line2')->nullable();
            $table->string('city', 120);
            $table->string('postal_code', 20);
            $table->string('region', 120)->nullable();
            $table->string('country', 2);
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('subtotal');
            $table->unsignedBigInteger('shipping_total');
            $table->unsignedBigInteger('tax_total');
            $table->unsignedBigInteger('total');
            $table->unsignedInteger('tax_rate')->default(0);
            $table->string('payment_gateway', 40);
            $table->string('payment_reference')->nullable()->index();
            $table->boolean('stock_reduced')->default(false);
            $table->timestamp('paid_at')->nullable();
            $table->timestamps();
        });

        Schema::create('shop_order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('shop_orders')->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained('shop_products')->nullOnDelete();
            $table->foreignId('variant_id')->nullable()->constrained('shop_product_variants')->nullOnDelete();
            $table->string('name');
            $table->string('variant_label', 130)->nullable();
            $table->string('sku', 64)->nullable();
            $table->unsignedBigInteger('unit_price');
            $table->unsignedInteger('quantity');
            $table->unsignedBigInteger('line_total');
            $table->timestamps();
        });

        Schema::create('shop_order_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->constrained('shop_orders')->cascadeOnDelete();
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20);
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('note')->nullable();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_order_status_changes');
        Schema::dropIfExists('shop_order_items');
        Schema::dropIfExists('shop_orders');
    }
};
