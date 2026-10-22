<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Izinkan akses panel Filament.
     *
     * Filament (v4/v5) hanya mengizinkan pengguna yang modelnya TIDAK
     * mengimplementasikan FilamentUser saat APP_ENV=local. Tanpa kontrak ini,
     * akun hasil `make:filament-user` akan ditolak (403) di staging/produksi.
     * Implementasi ini membuat akun berlaku di semua environment.
     */
    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin';
    }
}
