<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

require __DIR__ . '/settings.php';

Route::get('/chats', \App\Livewire\ChatSettings::class)->middleware(['auth'])->name('chat.settings');
Route::get('/bot-settings', \App\Livewire\BotSettings::class)->middleware(['auth'])->name('bot.settings');
Route::get('/bot-rules', \App\Livewire\BotResponseRules::class)->middleware(['auth'])->name('bot.rules');
Route::get('/chats-monitor', \App\Livewire\ChatList::class)->middleware(['auth'])->name('chat.list');
Route::get('/chats-monitor/{chat}', \App\Livewire\ChatDetail::class)->middleware(['auth'])->name('chat.detail');
Route::get('/followup-campaigns', \App\Livewire\FollowupCampaigns::class)->middleware(['auth'])->name('followup.campaigns');
Route::get('/automation-rules', \App\Livewire\AutomationRules::class)->middleware(['auth'])->name('automation.rules');
Route::get('/contact-followups', \App\Livewire\ContactFollowups::class)->middleware(['auth'])->name('contact.followups');
Route::get('/marketing', \App\Livewire\MarketingBlaster::class)->middleware(['auth'])->name('marketing');
Route::get('/phone-manager', \App\Livewire\PhoneNormalizationManager::class)->middleware(['auth'])->name('phone.manager');

// Ruta de emergencia para corregir enlaces de Google Meet en la instrucción del bot
Route::get('/admin/fix-meet-links', function () {
    $setting = \App\Models\BotSetting::find('system_instruction');
    if (!$setting) {
        return response('ERROR: No se encontró system_instruction en bot_settings.', 500);
    }

    $oldLinks = ['noe-kvxm-wxq', 'qcn-wfhf-gar'];
    $newLink = 'xzp-mkpf-oqh'; // << ENLACE ACTUAL CORRECTO

    $originalText = $setting->value;
    $newText = $originalText;
    $totalFixed = 0;

    foreach ($oldLinks as $old) {
        $count = substr_count($newText, $old);
        if ($count > 0) {
            $newText = str_replace($old, $newLink, $newText);
            $totalFixed += $count;
        }
    }

    if ($totalFixed > 0) {
        $setting->update(['value' => $newText]);
        \Illuminate\Support\Facades\Cache::forget('system_instruction');
        return response("✅ ÉXITO: Se corrigieron {$totalFixed} ocurrencia(s) del enlace antiguo.\nAhora el bot usará: https://meet.google.com/{$newLink}", 200)
            ->header('Content-Type', 'text/plain');
    }

    return response("✅ No se encontraron enlaces antiguos. La BD ya estaba correcta.\nEnlace activo: https://meet.google.com/{$newLink}", 200)
        ->header('Content-Type', 'text/plain');
})->middleware(['auth']);
