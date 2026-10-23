<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Self-Registration API
    |--------------------------------------------------------------------------
    |
    | Proyek eksternal (mis. agen xishie di http://localhost:8208/) dapat
    | mendaftarkan dirinya sendiri lewat POST /api/v1/external/register.
    | Endpoint ini non-publik: wajib menyertakan header X-Registration-Secret
    | yang cocok dengan nilai berikut. Biarkan kosong untuk menonaktifkan
    | self-registration (registrasi via UI tetap berfungsi).
    |
    */

    'registration_secret' => env('XISHIE_REGISTRATION_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Handshake
    |--------------------------------------------------------------------------
    |
    | Batas waktu (detik) probe handshake end-to-end ke proyek target.
    |
    */

    'handshake_timeout' => (int) env('XISHIE_HANDSHAKE_TIMEOUT', 5),

];
