<?php

declare(strict_types=1);

namespace Noblogs\Core;

/**
 * Sessione PHP con impostazioni prudenti e messaggi flash.
 *
 * Il cookie di sessione è limitato al dominio principale: i blog pubblici non
 * ne ricevono copia, così la navigazione dei lettori resta senza cookie.
 */
final class Session
{
    private static bool $started = false;

    public static function start(): void
    {
        if (self::$started || session_status() === PHP_SESSION_ACTIVE) {
            self::$started = true;
            return;
        }

        $secure = (bool) Config::get('site.https', true);

        session_name('noblogs_session');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'domain'   => '',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        $savePath = NOBLOGS_STORAGE . '/sessions';
        if (is_dir($savePath) && is_writable($savePath)) {
            session_save_path($savePath);
        }

        session_start();
        self::$started = true;

        // Rigenerazione periodica dell'id per limitare la finestra utile di un
        // eventuale furto di sessione.
        $now = time();
        if (!isset($_SESSION['_created_at'])) {
            $_SESSION['_created_at'] = $now;
        } elseif ($now - (int) $_SESSION['_created_at'] > 1800) {
            session_regenerate_id(true);
            $_SESSION['_created_at'] = $now;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        self::start();
        return $_SESSION[$key] ?? $default;
    }

    public static function put(string $key, mixed $value): void
    {
        self::start();
        $_SESSION[$key] = $value;
    }

    public static function forget(string $key): void
    {
        self::start();
        unset($_SESSION[$key]);
    }

    public static function regenerate(): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['_created_at'] = time();
    }

    public static function destroy(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name() ?: 'noblogs_session', '', [
                'expires'  => time() - 42000,
                'path'     => $params['path'],
                'domain'   => $params['domain'],
                'secure'   => $params['secure'],
                'httponly' => $params['httponly'],
                'samesite' => $params['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
        self::$started = false;
    }

    public static function flash(string $type, string $message): void
    {
        self::start();
        $_SESSION['_flash'][] = ['type' => $type, 'message' => $message];
    }

    /** @return list<array{type:string,message:string}> */
    public static function takeFlash(): array
    {
        self::start();
        $messages = $_SESSION['_flash'] ?? [];
        unset($_SESSION['_flash']);
        return is_array($messages) ? $messages : [];
    }

    /**
     * Conserva i valori di un form fallito, per ripopolarlo dopo il redirect.
     *
     * @param array<string,mixed> $values
     */
    public static function flashInput(array $values): void
    {
        self::put('_old_input', $values);
    }

    /** @return array<string,mixed> */
    public static function takeOldInput(): array
    {
        $values = self::get('_old_input', []);
        self::forget('_old_input');
        return is_array($values) ? $values : [];
    }
}
