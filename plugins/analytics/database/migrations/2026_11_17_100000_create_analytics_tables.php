<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Tables of the Analytics plugin: raw page views, daily totals and the daily salts.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('analytics_events', function (Blueprint $table) {
            $table->id();
            $table->date('day')->index();
            $table->string('path', 255);
            $table->string('referrer_host', 255)->nullable();
            $table->string('device', 16);
            $table->char('visitor', 64);
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('analytics_daily', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            $table->string('dimension', 16);
            $table->string('value', 255)->default('');
            $table->unsignedInteger('views')->default(0);
            $table->unsignedInteger('visitors')->default(0);
            $table->unique(['day', 'dimension', 'value']);
            $table->index(['dimension', 'day']);
        });

        Schema::create('analytics_salts', function (Blueprint $table) {
            $table->id();
            $table->date('day')->unique();
            $table->string('salt', 64);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_salts');
        Schema::dropIfExists('analytics_daily');
        Schema::dropIfExists('analytics_events');
    }
};
