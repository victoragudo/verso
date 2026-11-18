<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Menu item options: a mega menu flag, rel words for links and who can see the item.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->boolean('mega')->default(false);
            $table->string('rel', 64)->nullable();
            $table->string('visibility', 16)->default('everyone');
        });
    }

    public function down(): void
    {
        Schema::table('menu_items', function (Blueprint $table) {
            $table->dropColumn(['mega', 'rel', 'visibility']);
        });
    }
};
