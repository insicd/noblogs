<?php

declare(strict_types=1);

namespace Noblogs\Core;

/**
 * Token anti-CSRF per i form autenticati.
 */
final class Csrf
{
    private const KEY = '_csrf_token';

    public static function token(): string
    {
        $token = Session::get(self::KEY);
        if (!is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            Session::put(self::KEY, $token);
        }
        return $token;
    }

    public static function field(): string
    {
        return '<input type="hidden" name="_token" value="' . e(self::token()) . '">';
    }

    public static function check(Request $request): bool
    {
        $submitted = $request->input('_token') ?? $request->header('X-CSRF-Token');
        $expected = Session::get(self::KEY);

        return is_string($submitted)
            && is_string($expected)
            && $expected !== ''
            && hash_equals($expected, $submitted);
    }

    /**
     * Firma un valore con la chiave applicativa. Serve per i token che devono
     * viaggiare senza sessione (upvote, conferme via email, anteprime).
     */
    public static function sign(string $value, int $ttlSeconds = 0): string
    {
        $expiry = $ttlSeconds > 0 ? time() + $ttlSeconds : 0;
        $payload = $value . '|' . $expiry;
        return $expiry . '.' . self::hmac($payload);
    }

    public static function verifySigned(string $value, string $signature): bool
    {
        if (!str_contains($signature, '.')) {
            return false;
        }
        [$expiry, $hash] = explode('.', $signature, 2);
        if (!ctype_digit($expiry)) {
            return false;
        }
        if ((int) $expiry !== 0 && (int) $expiry < time()) {
            return false;
        }
        return hash_equals(self::hmac($value . '|' . $expiry), $hash);
    }

    public static function hmac(string $payload): string
    {
        return hash_hmac('sha256', $payload, (string) Config::get('security.app_key', 'noblogs'));
    }
}
