<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

require __DIR__.'/settings.php';

Route::get('/chats', \App\Livewire\ChatSettings::class)->middleware(['auth'])->name('chat.settings');
Route::get('/bot-settings', \App\Livewire\BotSettings::class)->middleware(['auth'])->name('bot.settings');
Route::get('/bot-rules', \App\Livewire\BotResponseRules::class)->middleware(['auth'])->name('bot.rules');
Route::get('/chats-monitor', \App\Livewire\ChatList::class)->middleware(['auth'])->name('chat.list');
Route::get('/chats-monitor/{chat}', \App\Livewire\ChatDetail::class)->middleware(['auth'])->name('chat.detail');
