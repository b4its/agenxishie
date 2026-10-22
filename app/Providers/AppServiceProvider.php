<?php

namespace App\Providers;

use App\Domain\Assistant\OmnigensiChatClient;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(OmnigensiChatClient::class, function (): OmnigensiChatClient {
            $config = (array) config('omnigensi');

            return new OmnigensiChatClient(
                baseUrl: (string) ($config['base_url'] ?? 'http://localhost:8123'),
                password: (string) ($config['password'] ?? '123'),
                timeout: (int) ($config['timeout'] ?? 120),
                connectTimeout: (int) ($config['connect_timeout'] ?? 10),
                verifyTls: (bool) ($config['verify_tls'] ?? true),
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
