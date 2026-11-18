<?php

use App\Verso\Site\Http\Controllers\IndexNowKeyController;
use App\Verso\Site\Http\Controllers\RobotsController;
use App\Verso\Site\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('sitemap.xml', [SitemapController::class, 'index'])->name('sitemap.index');
Route::get('sitemap-{sitemap}.xml', [SitemapController::class, 'show'])->where('sitemap', '[a-z0-9_-]+')->name('sitemap.show');
Route::get('robots.txt', RobotsController::class)->name('robots');
Route::get('{key}.txt', IndexNowKeyController::class)
    ->where('key', '[A-Za-z0-9-]{'.(int) config('verso.seo.indexnow.key_min_length').','.(int) config('verso.seo.indexnow.key_max_length').'}')
    ->name('indexnow.key');
