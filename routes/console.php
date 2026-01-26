<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| Queue Worker para Hosting Compartido
|--------------------------------------------------------------------------
|
| Este schedule ejecuta el worker de cola cada minuto.
| En hosting compartido, configura un cron job que ejecute:
| * * * * * cd /ruta/a/tu/proyecto && php artisan schedule:run >> /dev/null 2>&1
|
*/
Schedule::command('queue:work --stop-when-empty --timeout=200 --tries=2')
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();
