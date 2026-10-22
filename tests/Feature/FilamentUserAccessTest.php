<?php

declare(strict_types=1);

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Support\Facades\Hash;

/**
 * Regresi: akun hasil `php artisan make:filament-user` harus bisa dipakai login
 * di panel admin pada SEMUA environment.
 *
 * Filament menganulir pengguna hanya bila model tidak mengimplementasikan
 * FilamentUser DAN APP_ENV bukan "local". Tanpa kontrak FilamentUser, akun
 * yang dibuat akan mendapat 403 di staging/produksi.
 */
it('implements the FilamentUser contract', function (): void {
    expect(new User)->toBeInstanceOf(FilamentUser::class);
});

it('allows access to the admin panel', function (): void {
    $user = User::factory()->create();
    $panel = Filament::getPanel('admin');

    expect($user->canAccessPanel($panel))->toBeTrue();
});

it('lets a filament-user style account authenticate and reach the admin panel even when not local', function (): void {
    // Meniru `make:filament-user`: email belum diverifikasi.
    $user = User::factory()->unverified()->create([
        'email' => 'admin@xishie.test',
        'password' => Hash::make('rahasia123'),
    ]);

    expect($user->email_verified_at)->toBeNull();

    // Paksa environment non-local: tanpa implementasi FilamentUser, middleware
    // Filament akan mengembalikan 403 di sini.
    $this->app['config']->set('app.env', 'production');

    $this->actingAs($user);

    $this->get('/admin')->assertSuccessful();
});

it('denies access to panels other than admin', function (): void {
    $user = User::factory()->create();

    // canAccessPanel hanya mengizinkan panel "admin"; panel lain ditolak.
    $otherPanel = Mockery::mock(Panel::class);
    $otherPanel->shouldReceive('getId')->andReturn('other');

    expect($user->canAccessPanel($otherPanel))->toBeFalse();
});
