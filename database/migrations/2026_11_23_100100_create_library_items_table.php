<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The design library: blocks saved as synced blocks (one copy used in many places) or as patterns (copied when added).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('library_items', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 16);
            $table->string('title', 120);
            $table->string('category', 60)->default('');
            $table->json('keywords');
            $table->json('blocks');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['kind', 'title']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('library_items');
    }
};
