<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Categories, tags and other taxonomy terms.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('terms', function (Blueprint $table) {
            $table->id();
            $table->string('taxonomy', 64);
            $table->string('name');
            $table->string('slug', 191);
            $table->foreignId('parent_id')->nullable()->constrained('terms')->nullOnDelete();
            $table->text('description')->nullable();
            $table->timestamps();

            $table->unique(['taxonomy', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('terms');
    }
};
