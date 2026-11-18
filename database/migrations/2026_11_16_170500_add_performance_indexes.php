<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Indexes for the common public and admin queries. Plain column indexes work on SQLite, MySQL and PostgreSQL.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('entries', function (Blueprint $table) {
            $table->index(['type', 'updated_at'], 'entries_type_updated_at_index');
            $table->index(['status', 'type'], 'entries_status_type_index');
            $table->index(['parent_id', 'menu_order'], 'entries_parent_id_menu_order_index');
        });

        Schema::table('terms', function (Blueprint $table) {
            $table->index(['taxonomy', 'name'], 'terms_taxonomy_name_index');
        });
    }

    public function down(): void
    {
        Schema::table('terms', function (Blueprint $table) {
            $table->dropIndex('terms_taxonomy_name_index');
        });

        Schema::table('entries', function (Blueprint $table) {
            $table->dropIndex('entries_type_updated_at_index');
            $table->dropIndex('entries_status_type_index');
            $table->dropIndex('entries_parent_id_menu_order_index');
        });
    }
};
