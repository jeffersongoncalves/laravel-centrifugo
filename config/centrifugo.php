<?php

declare(strict_types=1);

return [

    /*
    | Centrifugo server HTTP API (reached from PHP) and its key (`http_api.key`).
    */
    'api_url' => env('CENTRIFUGO_API_URL', 'http://127.0.0.1:8000/api'),

    'api_key' => env('CENTRIFUGO_API_KEY'),

    /*
    | HS256 secret shared with Centrifugo (`client.token.hmac_secret_key`).
    | Signs both connection and channel subscription tokens.
    */
    'token_secret' => env('CENTRIFUGO_TOKEN_HMAC_SECRET'),

    /*
    | Lifetime, in seconds, of every token handed to the browser. centrifuge-js
    | refreshes expiring tokens on its own.
    */
    'token_ttl' => (int) env('CENTRIFUGO_TOKEN_TTL', 3600),

    /*
    | Public websocket URL for the browser. A bare host
    | (https://ws.example.com) gets wss:// and /connection/websocket; null =
    | the current origin's /connection/websocket (reverse proxy).
    */
    'url' => env('CENTRIFUGO_URL'),

    /*
    | Endpoint that signs subscription tokens: Laravel's own broadcasting
    | auth route (Broadcast::routes() / withBroadcasting()).
    */
    'auth_endpoint' => '/broadcasting/auth',

    /*
    | Guards allowed to request a connection token. The scripts component
    | picks one (`<x-centrifugo::scripts guard="admin" />`, default: the
    | first), so a `web` user and an `admin` (e.g. a Filament panel) logged in
    | on the same browser each connect as themselves.
    */
    'guards' => ['web'],

    /*
    | Route that signs connection tokens.
    */
    'connection_token_route' => [
        'uri' => 'broadcasting/centrifugo-token',
        'middleware' => ['web'],
    ],

];
