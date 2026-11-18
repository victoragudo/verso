<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Theme builder layouts: full templates, headers, footers and popups built with blocks, each with the
// conditions that say where it shows. "blocks" is the working copy, "published_blocks" what visitors see.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_layouts', function (Blueprint $table) {
            $table->id();
            $table->string('kind', 20);
            $table->string('name', 120);
            $table->json('blocks');
            $table->json('published_blocks')->nullable();
            $table->json('conditions');
            $table->json('settings');
            $table->json('published_settings')->nullable();
            $table->integer('priority')->default(0);
            $table->string('status', 16)->default('draft');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['kind', 'status']);
        });

        Schema::create('site_layout_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_layout_id')->constrained('site_layouts')->cascadeOnDelete();
            $table->json('blocks');
            $table->json('conditions');
            $table->json('settings');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_layout_revisions');
        Schema::dropIfExists('site_layouts');
    }
};
