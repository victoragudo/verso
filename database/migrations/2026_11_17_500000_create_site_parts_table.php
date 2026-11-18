<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Edited template parts (header, footer...) per theme. Theme files stay as they are; these rows win over them.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('site_parts', function (Blueprint $table) {
            $table->id();
            $table->string('theme', 100);
            $table->string('part', 100);
            $table->json('blocks');
            $table->json('published_blocks')->nullable();
            $table->string('status', 16)->default('draft');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->unique(['theme', 'part']);
        });

        Schema::create('site_part_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('site_part_id')->constrained('site_parts')->cascadeOnDelete();
            $table->json('blocks');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('site_part_revisions');
        Schema::dropIfExists('site_parts');
    }
};
