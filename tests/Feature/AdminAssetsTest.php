<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;

/**
 * Penjaga regresi untuk CSS panel admin.
 *
 * Bug yang pernah terjadi: /admin/login tampil polos karena resources/css/app.css
 * (dipakai sebagai viteTheme) tidak mengimpor tema Filament, sehingga kelas
 * .fi-* tidak ikut ter-bundle ke public/build/assets/app-*.css.
 */
it('imports the Filament theme in the vite theme stylesheet', function (): void {
    $css = File::get(resource_path('css/app.css'));

    expect($css)->toContain('vendor/filament/filament/resources/css/theme.css');
});

it('registers the app stylesheet as the panel vite theme', function (): void {
    $provider = File::get(app_path('Providers/Filament/AdminPanelProvider.php'));

    expect($provider)->toContain('viteTheme(')
        ->and($provider)->toContain('resources/css/app.css');
});

it('bundles filament .fi- classes into the compiled stylesheet', function (): void {
    $built = glob(public_path('build/assets/app-*.css')) ?: [];

    // Build belum dijalankan (mis. di CI ringan tanpa `npm run build`) —
    // lewati pemeriksaan biner agar tidak gagal palsu.
    if ($built === []) {
        $this->markTestSkipped('Aset Vite belum dibangun (jalankan: make build / make fix-assets).');
    }

    $contents = File::get($built[0]);

    expect($contents)->toContain('.fi-');
});
