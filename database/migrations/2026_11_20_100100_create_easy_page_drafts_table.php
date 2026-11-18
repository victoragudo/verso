<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Automatic drafts of the visual page editor: unsaved blocks per page and person, never shown on the site.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('easy_page_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('entries')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('blocks');
            $table->timestamps();
            $table->unique(['entry_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('easy_page_drafts');
    }
};
