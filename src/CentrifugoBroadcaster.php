<?php

declare(strict_types=1);

namespace JeffersonGoncalves\Centrifugo;

use Illuminate\Broadcasting\Broadcasters\Broadcaster;
use Illuminate\Broadcasting\Broadcasters\UsePusherChannelConventions;
use Illuminate\Broadcasting\BroadcastException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

/**
 * Laravel broadcaster for Centrifugo (https://centrifugal.dev).
 *
 * Publishes through Centrifugo's server HTTP API and authorizes private
 * channels with expiring HS256 subscription tokens, so routes/channels.php
 * callbacks keep deciding who may listen. Channel names keep Laravel's
 * `private-` prefix, matching the bundled Echo stand-in.
 */
class CentrifugoBroadcaster extends Broadcaster
{
    use UsePusherChannelConventions;

    public function __construct(
        private readonly string $apiUrl,
        private readonly string $apiKey,
        private readonly string $tokenSecret,
        private readonly int $tokenTtl = 3600,
    ) {}

    /**
     * @param  Request  $request
     */
    public function auth($request): mixed
    {
        $channel = (string) $request->input('channel_name');
        $channelName = $this->normalizeChannelName($channel);

        if ($channel === '' || ($this->isGuardedChannel($channel) && ! $this->retrieveUser($request, $channelName))) {
            throw new AccessDeniedHttpException;
        }

        return parent::verifyUserCanAccessChannel($request, $channelName);
    }

    /**
     * @param  Request  $request
     * @return array{token: string}
     */
    public function validAuthenticationResponse($request, $result): array
    {
        $channel = (string) $request->input('channel_name');
        /** @var Authenticatable $user */
        $user = $this->retrieveUser($request, $this->normalizeChannelName($channel));

        return ['token' => $this->subscriptionToken($user, $channel)];
    }

    /**
     * Centrifugo user id, Laravel channel style (`App.Models.Admin.1`), so a
     * User #1 and an Admin #1 are different Centrifugo users.
     */
    public static function userId(Authenticatable $user): string
    {
        return str_replace('\\', '.', $user::class).'.'.$user->getAuthIdentifier();
    }

    /**
     * Connection JWT. Its `sub` must match the subscription tokens' `sub`.
     */
    public function connectionToken(Authenticatable $user): string
    {
        return $this->sign([
            'sub' => self::userId($user),
            'exp' => time() + $this->tokenTtl,
        ]);
    }

    public function subscriptionToken(Authenticatable $user, string $channel): string
    {
        return $this->sign([
            'sub' => self::userId($user),
            'channel' => $channel,
            'exp' => time() + $this->tokenTtl,
        ]);
    }

    /**
     * @param  array<int, mixed>  $channels
     * @param  array<string, mixed>  $payload
     */
    public function broadcast(array $channels, $event, array $payload = []): void
    {
        if ($channels === []) {
            return;
        }

        Arr::forget($payload, 'socket');

        $response = Http::withHeaders(['X-API-Key' => $this->apiKey])
            ->timeout(5)
            ->post(rtrim($this->apiUrl, '/').'/broadcast', [
                'channels' => $this->formatChannels($channels),
                'data' => ['event' => $event, 'data' => $payload],
            ]);

        if ($response->failed() || $response->json('error') !== null) {
            throw new BroadcastException('Centrifugo broadcast failed: '.$response->body());
        }
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function sign(array $claims): string
    {
        if ($this->tokenSecret === '') {
            throw new BroadcastException('Centrifugo token secret is not configured (CENTRIFUGO_TOKEN_HMAC_SECRET).');
        }

        $b64 = static fn (string $value): string => rtrim(strtr(base64_encode($value), '+/', '-_'), '=');

        $unsigned = $b64('{"alg":"HS256","typ":"JWT"}').'.'.$b64(json_encode($claims, JSON_THROW_ON_ERROR));

        return $unsigned.'.'.$b64(hash_hmac('sha256', $unsigned, $this->tokenSecret, true));
    }
}
