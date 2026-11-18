<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Votes readers give to the next book of the month.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('book_club_votes', function (Blueprint $table) {
            $table->id();
            $table->string('book_title');
            $table->string('reader_email');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('book_club_votes');
    }
};
