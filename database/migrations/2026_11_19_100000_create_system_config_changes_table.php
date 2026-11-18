<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Audit log of the System settings area: who changed which setting, PHP limit file or permission.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('system_config_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action', 32)->index();
            $table->string('key', 190)->nullable()->index();
            $table->json('old_value')->nullable();
            $table->json('new_value')->nullable();
            $table->json('details')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('system_config_changes');
    }
};
