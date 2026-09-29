<?php

namespace App\Services;

use App\Models\Post;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PostPreviewTokenService
{
    public const EXCHANGE_PREFIX = 'post_preview_exchange:';
    public const ACCESS_PREFIX = 'post_preview_access:';

    public const TYPE_EXCHANGE = 'exchange';
    public const TYPE_ACCESS = 'access';

    public const EXCHANGE_TTL_SECONDS = 5;
    public const ACCESS_TTL_MINUTES = 60;

    /**
     * One-time exchange JWT. Valid for 5 seconds (Redis + exp).
     * $ip is stored for audit only (Nova click IP), not validated later.
     */
    public function createExchangeCode(Post $post, int $userId, string $ip): string
    {
        $ttl = self::EXCHANGE_TTL_SECONDS;
        $token = $this->mintJwt([
            'typ' => self::TYPE_EXCHANGE,
            'post_id' => (int) $post->id,
            'user_id' => $userId,
            'ip' => $ip,
            'jti' => Str::random(32),
            'iat' => time(),
            'exp' => time() + $ttl,
        ]);

        Cache::put($this->cacheKey(self::EXCHANGE_PREFIX, $token), [
            'post_id' => (int) $post->id,
            'user_id' => $userId,
            'ip' => $ip,
        ], now()->addSeconds($ttl));

        return $token;
    }

    /**
     * Burn exchange JWT and issue access JWT. Returns null on failure.
     *
     * @return array{token: string, post_id: int, expires_in: int}|null
     */
    public function exchangeCode(string $code): ?array
    {
        $claims = $this->parseAndVerifyJwt($code);
        if (!$claims || ($claims['typ'] ?? null) !== self::TYPE_EXCHANGE) {
            return null;
        }

        $key = $this->cacheKey(self::EXCHANGE_PREFIX, $code);
        $payload = Cache::get($key);
        if (!is_array($payload) || empty($payload['post_id'])) {
            return null;
        }

        Cache::forget($key);

        $ip = (string) ($payload['ip'] ?? '');
        $expiresIn = self::ACCESS_TTL_MINUTES * 60;
        $token = $this->mintJwt([
            'typ' => self::TYPE_ACCESS,
            'post_id' => (int) $payload['post_id'],
            'user_id' => (int) ($payload['user_id'] ?? 0),
            'ip' => $ip,
            'jti' => Str::random(32),
            'iat' => time(),
            'exp' => time() + $expiresIn,
        ]);

        Cache::put($this->cacheKey(self::ACCESS_PREFIX, $token), [
            'post_id' => (int) $payload['post_id'],
            'user_id' => (int) ($payload['user_id'] ?? 0),
            'ip' => $ip,
        ], now()->addMinutes(self::ACCESS_TTL_MINUTES));

        return [
            'token' => $token,
            'post_id' => (int) $payload['post_id'],
            'expires_in' => $expiresIn,
        ];
    }

    /**
     * Validate access JWT via Redis (+ signature/exp). Return post id or null.
     */
    public function validateAccessToken(string $token): ?int
    {
        $claims = $this->parseAndVerifyJwt($token);
        if (!$claims || ($claims['typ'] ?? null) !== self::TYPE_ACCESS) {
            return null;
        }

        $payload = Cache::get($this->cacheKey(self::ACCESS_PREFIX, $token));
        if (!is_array($payload) || empty($payload['post_id'])) {
            return null;
        }

        return (int) $payload['post_id'];
    }

    /**
     * @param  array<string, mixed>  $claims
     */
    private function mintJwt(array $claims): string
    {
        $header = $this->base64UrlEncode(json_encode(['alg' => 'HS256', 'typ' => 'JWT'], JSON_THROW_ON_ERROR));
        $payload = $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', $header.'.'.$payload, $this->secret(), true)
        );

        return $header.'.'.$payload.'.'.$signature;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function parseAndVerifyJwt(string $token): ?array
    {
        $parts = explode('.', $token);
        if (count($parts) !== 3) {
            return null;
        }

        [$headerB64, $payloadB64, $signatureB64] = $parts;
        $expected = $this->base64UrlEncode(
            hash_hmac('sha256', $headerB64.'.'.$payloadB64, $this->secret(), true)
        );
        if (!hash_equals($expected, $signatureB64)) {
            return null;
        }

        $headerJson = $this->base64UrlDecode($headerB64);
        $payloadJson = $this->base64UrlDecode($payloadB64);
        if ($headerJson === null || $payloadJson === null) {
            return null;
        }

        try {
            $header = json_decode($headerJson, true, 512, JSON_THROW_ON_ERROR);
            $claims = json_decode($payloadJson, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        if (!is_array($header) || ($header['alg'] ?? null) !== 'HS256') {
            return null;
        }
        if (!is_array($claims)) {
            return null;
        }

        $exp = (int) ($claims['exp'] ?? 0);
        if ($exp < time()) {
            return null;
        }

        return $claims;
    }

    private function cacheKey(string $prefix, string $token): string
    {
        return $prefix.hash('sha256', $token);
    }

    private function secret(): string
    {
        return (string) config('app.key');
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): ?string
    {
        $remainder = strlen($value) % 4;
        if ($remainder) {
            $value .= str_repeat('=', 4 - $remainder);
        }
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        return $decoded === false ? null : $decoded;
    }
}
