<?php

use Illuminate\Database\Migrations\Migration;

// A migration that fails on purpose, to test the rollback.
return new class extends Migration
{
    public function up(): void
    {
        throw new RuntimeException('The donations table needs a currency column.');
    }

    public function down(): void {}
};
