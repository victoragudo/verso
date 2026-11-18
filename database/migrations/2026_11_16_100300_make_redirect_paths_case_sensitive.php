<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// MySQL compares text without case by default, so "/About-Us" and "/about-us" were the same redirect.
// A binary collation makes it compare paths like SQLite and Postgres do.
return new class extends Migration
{
    public function up(): void
    {
        $this->setCollation(DB::connection()->getConfig('charset').'_bin');
    }

    public function down(): void
    {
        $this->setCollation((string) DB::connection()->getConfig('collation'));
    }

    // Only MySQL and MariaDB need this.
    protected function setCollation(string $collation): void
    {
        if (! in_array(DB::connection()->getDriverName(), ['mysql', 'mariadb'], true)) {
            return;
        }

        Schema::table('redirects', function (Blueprint $table) use ($collation) {
            $table->string('from_path')->collation($collation)->change();
        });
    }
};
