<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// One run of the core updater, with every step it took.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('updates', function (Blueprint $table) {
            $table->id();
            $table->string('from_version', 64);
            $table->string('to_version', 64);
            $table->string('status', 16)->index();
            $table->string('trigger', 16);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('release');
            $table->json('steps')->nullable();
            $table->string('backup_path')->nullable();
            $table->boolean('allow_without_database_backup')->default(false);
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('updates');
    }
};
