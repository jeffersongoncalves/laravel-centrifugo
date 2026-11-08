<?php

declare(strict_types=1);

namespace JeffersonGoncalves\Centrifugo;

use Illuminate\Broadcasting\BroadcastManager;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class CentrifugoServiceProvider extends PackageServiceProvider
{
    public const SCRIPT = __DIR__.'/../resources/dist/centrifugo-echo.js';

    public function configurePackage(Package $package): void
    {
        $package
            ->name('centrifugo')
            ->hasConfigFile()
            ->hasViews()
            ->hasRoute('centrifugo');
    }

    public function packageRegistered(): void
    {
        // BROADCAST_CONNECTION=centrifugo works without editing config/broadcasting.php.
        if (! config()->has('broadcasting.connections.centrifugo')) {
            config()->set('broadcasting.connections.centrifugo', ['driver' => 'centrifugo']);
        }
    }

    public function packageBooted(): void
    {
        $this->app->make(BroadcastManager::class)->extend(
            'centrifugo',
            fn ($app, array $config): CentrifugoBroadcaster => new CentrifugoBroadcaster(
                (string) ($config['api_url'] ?? config('centrifugo.api_url')),
                (string) ($config['api_key'] ?? config('centrifugo.api_key')),
                (string) ($config['token_secret'] ?? config('centrifugo.token_secret')),
                (int) ($config['token_ttl'] ?? config('centrifugo.token_ttl', 3600)),
            ),
        );
    }
}
