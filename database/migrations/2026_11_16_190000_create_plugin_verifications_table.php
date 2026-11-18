<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Stored reports of the checks run on a plugin before it is installed.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plugin_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->index();
            $table->string('version', 60);
            $table->string('source', 16);
            $table->string('sha256', 64)->nullable();
            $table->uuid('staging_token')->nullable()->index();
            $table->string('risk', 8);
            $table->unsignedInteger('score')->default(0);
            $table->json('findings');
            $table->json('details')->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('installed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plugin_verifications');
    }
};
