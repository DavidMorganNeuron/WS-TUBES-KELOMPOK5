<?php

use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\DestinationController;
use App\Http\Controllers\DestinationRdfController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MapController;
use App\Http\Controllers\RouteController;
use Illuminate\Support\Facades\Route;

Route::get('/', [HomeController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('home');

Route::get('/destinations', [DestinationController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('destinations');

Route::get('/destinations/live', [DestinationController::class, 'live'])
    ->middleware('throttle:60,1')
    ->name('destinations.live');

Route::get('/destinations/suggest', [DestinationController::class, 'suggest'])
    ->middleware('throttle:60,1')
    ->name('destinations.suggest');

Route::get('/destinations/{id}/rdf.{format}', DestinationRdfController::class)
    ->where(['id' => 'ws-\d{3}', 'format' => '[A-Za-z0-9]+'])
    ->middleware('throttle:60,1')
    ->name('destination.rdf');

Route::get('/destinations/{id}', [DestinationController::class, 'show'])
    ->where('id', 'ws-\d{3}')
    ->middleware('throttle:60,1')
    ->name('destination.detail');

Route::get('/map', [MapController::class, 'index'])
    ->middleware('throttle:60,1')
    ->name('map');

Route::get('/map/live', [MapController::class, 'live'])
    ->middleware('throttle:60,1')
    ->name('map.live');

Route::post('/routes/compute', [RouteController::class, 'compute'])
    ->middleware('throttle:20,1')
    ->name('routes.compute');

Route::post('/chatbot/send', [ChatbotController::class, 'chat'])
    ->middleware('throttle:10,1')
    ->name('chatbot.send');
