<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Custom field groups made in the admin. Fields and location rules are JSON.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('field_groups', function (Blueprint $table) {
            $table->id();
            $table->string('key', 80)->unique();
            $table->string('title', 160);
            $table->text('description')->nullable();
            $table->string('position', 16)->default('main');
            $table->integer('menu_order')->default(0);
            $table->boolean('active')->default(true);
            $table->json('location');
            $table->json('fields');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('field_groups');
    }
};
