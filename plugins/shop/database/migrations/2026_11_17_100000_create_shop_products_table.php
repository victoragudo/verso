<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Shop data of product entries: price, SKU, stock and gallery.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shop_products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->unique()->constrained('entries')->cascadeOnDelete();
            $table->unsignedBigInteger('price')->default(0);
            $table->string('sku', 64)->nullable()->unique();
            $table->boolean('track_stock')->default(false);
            $table->integer('stock')->default(0);
            $table->json('gallery')->nullable();
            $table->timestamps();
        });

        Schema::create('shop_product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained('shop_products')->cascadeOnDelete();
            $table->string('option_name', 60);
            $table->string('option_value', 60);
            $table->string('sku', 64)->nullable()->unique();
            $table->unsignedBigInteger('price')->nullable();
            $table->integer('stock')->default(0);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_product_variants');
        Schema::dropIfExists('shop_products');
    }
};
