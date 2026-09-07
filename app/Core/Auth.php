<?php

declare(strict_types=1);

namespace Noblogs\Core;

use Noblogs\Models\User;

/**
 * Autenticazione basata su sessione.
 */
final class Auth
{
    private const KEY = '_user_id';

    private static ?User $cached = null;

    private static bool $resolved = false;

    public static function user(): ?User
    {
        if (self::$resolved) {
            return self::$cached;
        }
        self::$resolved = true;

        $id = Session::get(self::KEY);
        if (!is_int($id) && !ctype_digit((string) $id)) {
            return self::$cached = null;
        }

        $user = User::find((int) $id);
        if ($user === null || !$user->is_active) {
            self::logout();
            return self::$cached = null;
        }

        return self::$cached = $user;
    }

    public static function check(): bool
    {
        return self::user() !== null;
    }

    public static function id(): ?int
    {
        return self::user()?->id;
    }

    public static function login(User $user): void
    {
        Session::regenerate();
        Session::put(self::KEY, $user->id);
        self::$cached = $user;
        self::$resolved = true;
        $user->touchLogin();
    }

    public static function logout(): void
    {
        Session::forget(self::KEY);
        self::$cached = null;
        self::$resolved = true;
    }

    /**
     * Verifica le credenziali. L'hash viene ricalcolato in modo trasparente se
     * i parametri di costo di PHP sono cambiati dall'ultimo accesso.
     */
    public static function attempt(string $email, string $password): ?User
    {
        $user = User::findByEmail($email);

        if ($user === null) {
            // Confronto fittizio: il tempo di risposta non deve rivelare se
            // l'indirizzo è registrato.
            password_verify($password, '$2y$12$usesomesillystringfore7hnbRJHxXVLeakoG8K30oukPsA.ztMG');
            return null;
        }

        if (!password_verify($password, $user->password_hash)) {
            return null;
        }

        if (password_needs_rehash($user->password_hash, PASSWORD_DEFAULT)) {
            $user->setPassword($password);
        }

        return $user->is_active ? $user : null;
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }
}
