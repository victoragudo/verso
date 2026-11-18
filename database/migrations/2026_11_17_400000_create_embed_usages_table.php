<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Which entries embed which plugin items, as a block or as a shortcode. Rebuilt when an entry is saved.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('embed_usages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('entries')->cascadeOnDelete();
            $table->string('kind', 16);
            $table->string('name', 100);
            $table->string('key', 190)->nullable();
            $table->timestamps();

            $table->index(['name', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('embed_usages');
    }
};
