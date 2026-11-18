<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

// Adds social links and a slug for the public author page to users. Existing users get a slug from their name.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->json('social_links')->nullable();
            $table->string('slug', 190)->nullable()->unique();
        });

        $taken = [];

        foreach (DB::table('users')->orderBy('id')->get(['id', 'name']) as $user) {
            $base = Str::slug((string) $user->name) ?: 'author';
            $slug = $base;

            for ($number = 2; isset($taken[$slug]); $number++) {
                $slug = $base.'-'.$number;
            }

            $taken[$slug] = true;
            DB::table('users')->where('id', $user->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['social_links', 'slug']);
        });
    }
};
