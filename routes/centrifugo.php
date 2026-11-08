<?php

declare(strict_types=1);

use Illuminate\Auth\AuthenticationException;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use JeffersonGoncalves\Centrifugo\CentrifugoBroadcaster;
use JeffersonGoncalves\Centrifugo\CentrifugoServiceProvider;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/** @var array{uri: string, middleware: list<string>} $tokenRoute */
$tokenRoute = config('centrifugo.connection_token_route');

Route::middleware($tokenRoute['middleware'])->group(function () use ($tokenRoute): void {
    Route::post($tokenRoute['uri'], function (Request $request): array {
        /** @var list<string> $guards */
        $guards = config('centrifugo.guards', ['web']);
        $guard = $request->query('guard', $guards[0] ?? null);

        if (! is_string($guard) || ! in_array($guard, $guards, true)) {
            throw new AccessDeniedHttpException;
        }

        $user = auth($guard)->user() ?? throw new AuthenticationException(guards: [$guard]);
        $broadcaster = app(BroadcastManager::class)->connection('centrifugo');

        if (! $broadcaster instanceof CentrifugoBroadcaster) {
            throw new AccessDeniedHttpException;
        }

        return ['token' => $broadcaster->connectionToken($user)];
    })->name('centrifugo.connection-token');
});

// Public, static and versioned by ?id=<md5> in the component, so it caches for a year.
Route::get('centrifugo/echo.js', fn (): BinaryFileResponse => response()->file(CentrifugoServiceProvider::SCRIPT, [
    'Content-Type' => 'application/javascript; charset=utf-8',
    'Cache-Control' => 'public, max-age=31536000, immutable',
]))->name('centrifugo.script');
