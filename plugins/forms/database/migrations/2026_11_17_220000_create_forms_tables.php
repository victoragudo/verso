<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tables of the Forms plugin: the forms and the messages people send with them.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forms', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->json('fields');
            $table->json('settings');
            $table->timestamps();
        });

        Schema::create('form_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('form_id')->constrained('forms')->cascadeOnDelete();
            $table->string('status', 20)->default('new')->index();
            $table->json('data');
            $table->json('files')->nullable();
            $table->string('email')->nullable()->index();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->string('page_url', 2048)->nullable();
            $table->string('spam_reason', 40)->nullable();
            $table->text('search_text')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
            $table->index(['form_id', 'status', 'id']);
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submissions');
        Schema::dropIfExists('forms');
    }
};
