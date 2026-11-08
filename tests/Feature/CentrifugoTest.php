<?php

declare(strict_types=1);

use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Http;
use JeffersonGoncalves\Centrifugo\CentrifugoBroadcaster;
use JeffersonGoncalves\Centrifugo\CentrifugoServiceProvider;
use JeffersonGoncalves\Centrifugo\Tests\Fixtures\Admin;

function user(int $id = 1): User
{
    return (new User)->forceFill(['id' => $id]);
}

/** @return array<string, mixed> */
function claims(string $jwt): array
{
    [$header, $payload, $signature] = explode('.', $jwt);
    $b64 = fn (string $v): string => rtrim(strtr(base64_encode($v), '+/', '-_'), '=');

    expect($signature)->toBe($b64(hash_hmac('sha256', "{$header}.{$payload}", 'secret', true)));

    return json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
}

it('registers the centrifugo connection without editing broadcasting.php', function (): void {
    expect(config('broadcasting.connections.centrifugo.driver'))->toBe('centrifugo')
        ->and(Broadcast::connection('centrifugo'))->toBeInstanceOf(CentrifugoBroadcaster::class);
});

it('signs an expiring subscription token only for channels the user may join', function (): void {
    $this->actingAs(user(7));

    $token = $this->postJson('/broadcasting/auth', ['channel_name' => 'private-App.Models.User.7'])
        ->assertOk()
        ->json('token');

    expect(claims($token))
        ->sub->toBe('Illuminate.Foundation.Auth.User.7')
        ->channel->toBe('private-App.Models.User.7')
        ->exp->toBeGreaterThan(time());

    $this->postJson('/broadcasting/auth', ['channel_name' => 'private-App.Models.User.8'])->assertForbidden();
});

it('issues a connection token for the authenticated user', function (): void {
    $token = $this->actingAs(user(7))
        ->postJson('/broadcasting/centrifugo-token')
        ->assertOk()
        ->json('token');

    expect(claims($token))->sub->toBe('Illuminate.Foundation.Auth.User.7')->exp->toBeGreaterThan(time());
});

it('authorizes App.Models.Admin.{id} on the admin guard, apart from a same-id web user', function (): void {
    $admin = (new Admin)->forceFill(['id' => 7]);

    // Both logged in on one browser: each guard connects as its own Centrifugo user.
    $this->actingAs(user(7))->actingAs($admin, 'admin');

    $connection = claims($this->postJson('/broadcasting/centrifugo-token?guard=admin')->assertOk()->json('token'));
    $subscription = claims($this->postJson('/broadcasting/auth', ['channel_name' => 'private-App.Models.Admin.7'])->assertOk()->json('token'));
    $webConnection = claims($this->postJson('/broadcasting/centrifugo-token')->assertOk()->json('token'));

    expect($connection['sub'])->toBe('JeffersonGoncalves.Centrifugo.Tests.Fixtures.Admin.7')
        ->and($subscription['sub'])->toBe($connection['sub'])
        ->and($webConnection['sub'])->toBe('Illuminate.Foundation.Auth.User.7');
});

it('keeps a web user out of an admin channel', function (): void {
    $this->actingAs(user(7));

    $this->postJson('/broadcasting/auth', ['channel_name' => 'private-App.Models.Admin.7'])->assertForbidden();
    $this->postJson('/broadcasting/centrifugo-token?guard=admin')->assertUnauthorized();
});

it('refuses guards outside centrifugo.guards', function (): void {
    $this->actingAs(user(7));

    $this->postJson('/broadcasting/centrifugo-token?guard=api')->assertForbidden();
});

it('rejects guests on both token endpoints', function (): void {
    $this->postJson('/broadcasting/auth', ['channel_name' => 'private-App.Models.User.1'])->assertForbidden();
    $this->postJson('/broadcasting/centrifugo-token')->assertUnauthorized();
});

it('publishes through the Centrifugo HTTP API without the socket key', function (): void {
    Http::fake(['centrifugo.test/*' => Http::response(['result' => []])]);

    Broadcast::connection('centrifugo')->broadcast(
        [new PrivateChannel('App.Models.User.1')],
        'database-notifications.sent',
        ['foo' => 'bar', 'socket' => null],
    );

    Http::assertSent(fn ($request): bool => $request->url() === 'http://centrifugo.test/api/broadcast'
        && $request->header('X-API-Key') === ['api-key']
        && $request['channels'] === ['private-App.Models.User.1']
        && $request['data'] === ['event' => 'database-notifications.sent', 'data' => ['foo' => 'bar']]);
});

it('throws when Centrifugo answers with an error', function (): void {
    Http::fake(['centrifugo.test/*' => Http::response(['error' => ['code' => 102, 'message' => 'unknown channel']])]);

    Broadcast::connection('centrifugo')->broadcast([new PrivateChannel('x')], 'e', []);
})->throws(BroadcastException::class);

it('refuses to sign tokens without a secret', function (): void {
    (new CentrifugoBroadcaster('http://x', 'k', ''))->connectionToken(user());
})->throws(BroadcastException::class);

it('serves the bundled script with a long-lived cache', function (): void {
    $this->get('/centrifugo/echo.js')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/javascript; charset=utf-8')
        ->assertHeader('Cache-Control', 'immutable, max-age=31536000, public');
});

it('renders the scripts component with every endpoint', function (): void {
    config(['centrifugo.url' => 'https://ws.example.com']);

    $html = Blade::render('<x-centrifugo::scripts />');

    expect($html)
        ->toContain('/centrifugo/echo.js?id='.md5_file(CentrifugoServiceProvider::SCRIPT))
        ->toContain('data-url="https://ws.example.com"')
        ->toContain('data-auth-url="http://localhost/broadcasting/auth"')
        ->toContain('data-connection-token-url="http://localhost/broadcasting/centrifugo-token"');

    expect(Blade::render('<x-centrifugo::scripts guard="admin" />'))
        ->toContain('data-connection-token-url="http://localhost/broadcasting/centrifugo-token?guard=admin"');
});
