<?php

use App\Http\Controllers\WebhookController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// La URL final será: http://tu-sitio/api/evolution/webhook
Route::post('/evolution/webhook', [WebhookController::class, 'handle'])
    ->middleware('evolution.auth');

