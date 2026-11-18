<?php

use Illuminate\Support\Facades\Route;

Route::get('calendar/upcoming', fn () => 'upcoming')->name('login');
Route::get('feed', fn () => 'events feed')->name('calendar.feed');
