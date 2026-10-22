<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * Penjaga regresi Design System "Retro Blue".
 *
 * Memastikan tema token-driven tetap konsisten: setiap token warna punya nilai
 * untuk LIGHT (di `:root`) dan DARK (di `.dark`) sehingga tidak ada komponen
 * yang "bocor" warna antar tema (mis. teks putih di atas permukaan putih).
 */
function retroThemeCss(): string
{
    return File::get(resource_path('css/retro-blue-theme.css'));
}

it('defines both light (:root) and dark (.dark) token scopes', function (): void {
    $css = retroThemeCss();

    expect($css)->toMatch('/:root\s*\{/')
        ->and($css)->toMatch('/\.dark\s*\{/');
});

it('provides a dark override for every color token declared in :root', function (): void {
    $css = retroThemeCss();

    // Ambil blok :root dan .dark secara kasar.
    preg_match('/:root\s*\{(.*?)\n\}/s', $css, $root);
    preg_match('/\.dark\s*\{(.*?)\n\}/s', $css, $dark);

    $rootTokens = collect(explode("\n", $root[1] ?? ''))
        ->map(fn (string $line): ?string => preg_match('/^\s*(--rb-[a-z0-9-]+)\s*:/i', $line, $m) ? $m[1] : null)
        ->filter()
        ->values();

    $darkTokens = collect(explode("\n", $dark[1] ?? ''))
        ->map(fn (string $line): ?string => preg_match('/^\s*(--rb-[a-z0-9-]+)\s*:/i', $line, $m) ? $m[1] : null)
        ->filter()
        ->values();

    expect($rootTokens)->not->toBeEmpty();

    // Setiap token warna inti (surface/ink/status/tint/terminal) wajib punya
    // override dark. Token turunan (shadow/radius/border) mengacu ke token lain
    // sehingga otomatis beradaptasi dan tidak perlu diduplikasi.
    $colorTokens = $rootTokens->filter(
        fn (string $t): bool => (bool) preg_match('/--rb-(surface|canvas|ink|primary|secondary|accent|status|tint|terminal|on-primary)/', $t),
    );

    $missing = $colorTokens->reject(fn (string $t): bool => $darkTokens->contains($t))->values()->all();

    expect($missing)->toBe([], 'Token warna tanpa override dark: '.implode(', ', $missing));
});

it('does not hardcode light-only hex colors inside component rules', function (): void {
    $css = retroThemeCss();

    // Komponen `.rb-*` hanya boleh memakai var(--rb-*) untuk warna, bukan hex
    // mentah (yang akan membuat text/bg bentrok di salah satu tema).
    // Pengecualian: #ffffff untuk teks pada titlebar/tabel header (kontras di
    // kedua tema) dan nilai token di blok :root/.dark.
    $body = preg_replace('/:root\s*\{.*?\n\}/s', '', $css);
    $body = preg_replace('/\.dark\s*\{.*?\n\}/s', '', $body ?? '');

    expect($body)->not->toBeNull();

    // Cari deklarasi color/background dengan hex di luar token scope, kecuali #ffffff.
    preg_match_all('/(?:color|background(?:-color)?)\s*:\s*(#[0-9a-fA-F]{3,8})/', $body ?? '', $matches);

    $offenders = collect($matches[1] ?? [])
        ->map(fn (string $hex): string => strtolower($hex))
        ->reject(fn (string $hex): bool => in_array($hex, ['#fff', '#ffffff'], true))
        ->unique()
        ->values()
        ->all();

    expect($offenders)->toBe([], 'Warna hex keras ditemukan di komponen: '.implode(', ', $offenders));
});

it('enables dark mode and the theme switcher on the admin panel', function (): void {
    $provider = File::get(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($provider)->toContain('->darkMode(')
        ->and($provider)->toContain('->themeSwitcher(');
});
