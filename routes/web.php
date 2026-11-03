<?php

use App\Http\Controllers\DestinationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MapController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])->name('home');

Route::get('/destinations', [DestinationController::class, 'index'])->name('destinations');

Route::get('/destinations/{id}', [DestinationController::class, 'show'])
    ->where('id', '[A-Za-z0-9_\-]+')
    ->name('destination.detail');

Route::get('/map', [MapController::class, 'index'])->name('map');
