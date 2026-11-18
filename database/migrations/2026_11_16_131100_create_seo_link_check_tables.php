<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Results of the link check: one report per published entry and the internal links found in it.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_page_reports', function (Blueprint $table) {
            $table->foreignId('entry_id')->primary()->constrained('entries')->cascadeOnDelete();
            $table->unsignedInteger('render_ms')->default(0);
            $table->unsignedInteger('internal_links')->default(0);
            $table->unsignedInteger('external_links')->default(0);
            $table->unsignedInteger('broken_links')->default(0);
            $table->timestamp('checked_at')->nullable();
        });

        Schema::create('seo_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('source_entry_id')->constrained('entries')->cascadeOnDelete();
            $table->string('path');
            $table->foreignId('target_entry_id')->nullable()->constrained('entries')->nullOnDelete();
            $table->string('status', 16);
            $table->index(['status', 'source_entry_id']);
            $table->index('target_entry_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_links');
        Schema::dropIfExists('seo_page_reports');
    }
};
