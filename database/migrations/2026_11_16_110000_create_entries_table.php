<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Posts, pages and every other content type.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entries', function (Blueprint $table) {
            $table->id();
            $table->string('type', 64);
            $table->string('title');
            $table->string('slug', 191);
            $table->string('status', 32)->default('draft');
            $table->json('blocks')->nullable();
            $table->text('excerpt')->nullable();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('entries')->nullOnDelete();
            $table->integer('menu_order')->default(0);
            $table->timestamp('published_at')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['type', 'slug', 'parent_id']);
            $table->index(['type', 'status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entries');
    }
};
