<?php

declare(strict_types=1);

namespace Noblogs\Core;

/**
 * Limitatore di frequenza su tabella, condiviso tra i processi PHP.
 *
 * Le chiavi contengono un hash dell'IP, mai l'IP in chiaro.
 */
final class RateLimiter
{
    /**
     * Registra un tentativo e dice se il limite è stato superato.
     *
     * @param string $key      Identificativo del secchiello, es. 'login:<hash>'.
     * @param int    $limit    Tentativi consentiti nella finestra.
     * @param int    $seconds  Ampiezza della finestra.
     */
    public static function tooManyAttempts(string $key, int $limit, int $seconds): bool
    {
        $bucket = self::bucket($key);
        $db = Database::instance();

        try {
            $now = gmdate('Y-m-d H:i:s');
            $expiresAt = gmdate('Y-m-d H:i:s', time() + $seconds);

            // Un solo statement: se la finestra è scaduta il contatore riparte,
            // altrimenti si incrementa. Evita la corsa tra SELECT e UPDATE.
            $db->query(
                'INSERT INTO {{rate_limits}} (bucket, counter, expires_at)
                 VALUES (:bucket, 1, :expires)
                 ON DUPLICATE KEY UPDATE
                    counter = IF(expires_at < :now, 1, counter + 1),
                    expires_at = IF(expires_at < :now2, :expires2, expires_at)',
                [
                    'bucket'   => $bucket,
                    'expires'  => $expiresAt,
                    'expires2' => $expiresAt,
                    'now'      => $now,
                    'now2'     => $now,
                ]
            );

            $counter = (int) $db->fetchColumn(
                'SELECT counter FROM {{rate_limits}} WHERE bucket = ?',
                [$bucket]
            );

            self::pruneOccasionally();

            return $counter > $limit;
        } catch (\Throwable $e) {
            // Un problema sul limitatore non deve impedire l'uso del sito.
            ErrorHandler::log($e);
            return false;
        }
    }

    public static function clear(string $key): void
    {
        try {
            Database::instance()->delete('rate_limits', 'bucket = ?', [self::bucket($key)]);
        } catch (\Throwable $e) {
            ErrorHandler::log($e);
        }
    }

    public static function remaining(string $key, int $limit): int
    {
        try {
            $counter = (int) Database::instance()->fetchColumn(
                'SELECT counter FROM {{rate_limits}} WHERE bucket = ? AND expires_at >= UTC_TIMESTAMP()',
                [self::bucket($key)]
            );
            return max(0, $limit - $counter);
        } catch (\Throwable) {
            return $limit;
        }
    }

    public static function hashIp(string $ip): string
    {
        return substr(hash_hmac('sha256', $ip, (string) Config::get('security.app_key', 'noblogs')), 0, 32);
    }

    private static function bucket(string $key): string
    {
        return mb_substr($key, 0, 180);
    }

    private static function pruneOccasionally(): void
    {
        if (random_int(1, 200) !== 1) {
            return;
        }
        Database::instance()->query('DELETE FROM {{rate_limits}} WHERE expires_at < UTC_TIMESTAMP()');
    }
}
