<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The second statement adds a column to a table that does not exist.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('donations', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('amount');
        });

        Schema::table('donation_currencies', function (Blueprint $table) {
            $table->string('code', 3);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('donations');
    }
};
