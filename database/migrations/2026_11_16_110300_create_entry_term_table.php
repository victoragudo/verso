<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Links entries to terms.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entry_term', function (Blueprint $table) {
            $table->foreignId('entry_id')->constrained('entries')->cascadeOnDelete();
            $table->foreignId('term_id')->constrained('terms')->cascadeOnDelete();

            $table->primary(['entry_id', 'term_id']);
            $table->index('term_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('entry_term');
    }
};
