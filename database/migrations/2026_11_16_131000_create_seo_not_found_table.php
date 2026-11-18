<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Addresses visitors asked for that do not exist (the 404 monitor). No IP address or other personal data.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('seo_not_found', function (Blueprint $table) {
            $table->id();
            $table->string('path')->unique();
            $table->unsignedInteger('hits')->default(0);
            $table->string('referrer_host')->nullable();
            $table->timestamp('first_seen_at')->nullable();
            $table->timestamp('last_seen_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('seo_not_found');
    }
};
