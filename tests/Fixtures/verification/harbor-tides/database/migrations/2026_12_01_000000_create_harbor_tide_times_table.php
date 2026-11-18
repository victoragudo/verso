<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tide times for each harbour.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('harbor_tide_times', function (Blueprint $table) {
            $table->id();
            $table->string('harbour');
            $table->dateTime('time');
            $table->decimal('height', 5, 2);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('harbor_tide_times');
    }
};
