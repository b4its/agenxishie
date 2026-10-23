<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Jadwal pemantauan otomatis probe target proyek eksternal setiap 5 menit
Schedule::command('xishie:ping-clients')->everyFiveMinutes();
