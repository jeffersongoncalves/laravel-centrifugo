<?php

declare(strict_types=1);

namespace JeffersonGoncalves\Centrifugo\Tests;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Broadcast;
use JeffersonGoncalves\Centrifugo\CentrifugoServiceProvider;
use JeffersonGoncalves\Centrifugo\Tests\Fixtures\Admin;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app): array
    {
        return [
            CentrifugoServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
        $app['config']->set('broadcasting.default', 'centrifugo');
        $app['config']->set('centrifugo.api_url', 'http://centrifugo.test/api');
        $app['config']->set('centrifugo.api_key', 'api-key');
        $app['config']->set('centrifugo.token_secret', 'secret');
        $app['config']->set('auth.providers.users.model', User::class);
        // Second guard, the shape of a Filament panel on `admin`.
        $app['config']->set('auth.providers.admins', ['driver' => 'eloquent', 'model' => Admin::class]);
        $app['config']->set('auth.guards.admin', ['driver' => 'session', 'provider' => 'admins']);
        $app['config']->set('centrifugo.guards', ['web', 'admin']);
    }

    protected function defineRoutes($router): void
    {
        Broadcast::routes(['middleware' => ['web']]);

        Broadcast::channel('App.Models.User.{id}', fn (User $user, string $id): bool => (string) $user->getKey() === $id);
        Broadcast::channel('App.Models.Admin.{id}', fn (Admin $admin, string $id): bool => (string) $admin->getKey() === $id, ['guards' => ['admin']]);
    }
}
