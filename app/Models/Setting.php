<?php

declare(strict_types=1);

namespace Noblogs\Models;

use Noblogs\Core\Database;

/**
 * Impostazioni modificabili dall'amministrazione senza toccare i file.
 */
final class Setting
{
    /** @var array<string,string>|null */
    private static ?array $cache = null;

    public static function get(string $name, ?string $default = null): ?string
    {
        return self::all()[$name] ?? $default;
    }

    public static function bool(string $name, bool $default = false): bool
    {
        $value = self::get($name);
        return $value === null ? $default : in_array($value, ['1', 'true', 'yes', 'on'], true);
    }

    public static function put(string $name, ?string $value): void
    {
        Database::instance()->query(
            'INSERT INTO {{settings}} (name, value) VALUES (?, ?)
             ON DUPLICATE KEY UPDATE value = VALUES(value)',
            [$name, $value]
        );
        self::$cache = null;
    }

    /** @return array<string,string> */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $values = [];
        try {
            foreach (Database::instance()->fetchAll('SELECT name, value FROM {{settings}}') as $row) {
                $values[(string) $row['name']] = (string) ($row['value'] ?? '');
            }
        } catch (\Throwable) {
            // Prima dell'installazione la tabella non esiste ancora.
            return [];
        }

        return self::$cache = $values;
    }
}
