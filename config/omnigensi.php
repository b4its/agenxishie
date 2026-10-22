<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Omnigensi Backend (AI Chat / Multi-Agent Engine)
    |--------------------------------------------------------------------------
    |
    | Agen Xishie menautkan AI Chat & pemilihan model ke backend Omnigensi
    | (FastAPI). Base URL mengarah ke service backend omnigensi. Di dalam
    | jaringan Docker Compose xishie, gunakan host.docker.internal atau nama
    | host yang dapat dijangkau container.
    |
    */

    'base_url' => env('OMNIGENSI_BASE_URL', 'http://localhost:8123'),

    /*
    | Password operator tunggal Omnigensi (APP_PASSWORD di backend). Dipakai
    | untuk menukar sesi (bearer token) guna mengakses /chat/models dan
    | /chat/completions.
    */
    'password' => env('OMNIGENSI_PASSWORD', '123'),

    /*
    | Batas waktu permintaan (detik). Model LLM dapat lambat, jadi beri ruang
    | cukup untuk respons panjang.
    */
    'timeout' => (int) env('OMNIGENSI_TIMEOUT', 120),

    'connect_timeout' => (int) env('OMNIGENSI_CONNECT_TIMEOUT', 10),

    /*
    | Verifikasi TLS untuk endpoint berpindah (self-hosted). Default true.
    */
    'verify_tls' => (bool) env('OMNIGENSI_VERIFY_TLS', true),

];
