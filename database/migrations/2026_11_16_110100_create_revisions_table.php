<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Old versions of entries.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->constrained('entries')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->json('blocks')->nullable();
            $table->text('excerpt')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['entry_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('revisions');
    }
};
