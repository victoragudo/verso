<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Theme reports use the same table as plugin reports. The kind keeps a plugin and a theme with the same slug apart.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plugin_verifications', function (Blueprint $table) {
            $table->string('kind', 16)->default('plugin')->after('id');
            $table->index(['kind', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::table('plugin_verifications', function (Blueprint $table) {
            $table->dropIndex(['kind', 'slug']);
            $table->dropColumn('kind');
        });
    }
};
