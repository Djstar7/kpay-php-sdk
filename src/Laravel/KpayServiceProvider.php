<?php

declare(strict_types=1);

namespace Kpay\Sdk\Laravel;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;
use Kpay\Sdk\KpayClient;

class KpayServiceProvider extends ServiceProvider implements DeferrableProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/kpay.php', 'kpay');

        $this->app->singleton(KpayClient::class, function ($app): KpayClient {
            $c = $app['config']['kpay'];

            return new KpayClient(
                (string) $c['base_url'],
                (string) $c['api_key'],
                (string) $c['secret_key'],
                $c['gateway_secret'] !== null ? (string) $c['gateway_secret'] : null,
                (int) $c['max_duration'],
            );
        });

        $this->app->alias(KpayClient::class, 'kpay');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../../config/kpay.php' => $this->app->configPath('kpay.php'),
        ], 'kpay-config');
    }

    /** @return array<int,string> */
    public function provides(): array
    {
        return [KpayClient::class, 'kpay'];
    }
}
