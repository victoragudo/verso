<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Roles and permissions: custom roles and changed built-in roles, extra roles per user and single grants or denies.
// Existing users keep their role in users.role, so nothing has to be copied.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name', 120);
            $table->string('description', 500)->nullable();
            // Custom roles: every capability. Built-in roles: capabilities added to the defaults.
            $table->json('capabilities');
            // Built-in roles only: default capabilities that were taken away.
            $table->json('removed_capabilities')->nullable();
            $table->string('based_on', 64)->nullable();
            $table->timestamps();
        });

        Schema::create('role_user', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('role', 64)->index();
            $table->primary(['user_id', 'role']);
        });

        Schema::create('user_capabilities', function (Blueprint $table) {
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('capability', 120);
            $table->boolean('granted');
            $table->primary(['user_id', 'capability']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_capabilities');
        Schema::dropIfExists('role_user');
        Schema::dropIfExists('roles');
    }
};
