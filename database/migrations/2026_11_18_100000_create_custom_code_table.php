<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Custom CSS and JavaScript written by admins, for the whole site (entry_id null) or for one entry.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('custom_code', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entry_id')->nullable()->unique()->constrained('entries')->cascadeOnDelete();
            $table->longText('css')->nullable();
            $table->longText('js')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('custom_code');
    }
};
