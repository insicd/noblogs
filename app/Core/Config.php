<?php

declare(strict_types=1);

namespace Noblogs\Core;

/**
 * Configurazione dell'applicazione, letta da config/config.php.
 *
 * Le chiavi si leggono con la notazione puntata: Config::get('db.host').
 */
final class Config
{
    /** @var array<string,mixed> */
    private static array $values = [];

    private static bool $installed = false;

    public static function load(string $path): void
    {
        if (!is_file($path)) {
            self::$values = require NOBLOGS_ROOT . '/config/config.sample.php';
            self::$installed = false;
            return;
        }

        $values = require $path;
        if (!is_array($values)) {
            throw new \RuntimeException('config/config.php deve restituire un array.');
        }

        // I valori di default coprono le chiavi aggiunte dagli aggiornamenti e
        // assenti in una config generata da una versione precedente.
        $defaults = require NOBLOGS_ROOT . '/config/config.sample.php';
        self::$values = self::mergeDeep($defaults, $values);
        self::$installed = true;
    }

    public static function isInstalled(): bool
    {
        return self::$installed;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $node = self::$values;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($node) || !array_key_exists($segment, $node)) {
                return $default;
            }
            $node = $node[$segment];
        }
        return $node;
    }

    public static function set(string $key, mixed $value): void
    {
        $segments = explode('.', $key);
        $node = &self::$values;
        foreach ($segments as $segment) {
            if (!isset($node[$segment]) || !is_array($node[$segment])) {
                $node[$segment] = [];
            }
            $node = &$node[$segment];
        }
        $node = $value;
    }

    /** @return array<string,mixed> */
    public static function all(): array
    {
        return self::$values;
    }

    /**
     * Serializza una configurazione come file PHP restituibile.
     *
     * @param array<string,mixed> $values
     */
    public static function export(array $values): string
    {
        return "<?php\n\n// File generato dall'installer di Noblogs il "
            . gmdate('Y-m-d H:i:s') . " UTC.\n\nreturn "
            . self::exportValue($values, 0) . ";\n";
    }

    private static function exportValue(mixed $value, int $depth): string
    {
        $pad = str_repeat('    ', $depth + 1);
        $padEnd = str_repeat('    ', $depth);

        if (is_array($value)) {
            if ($value === []) {
                return '[]';
            }
            $isList = array_is_list($value);
            $lines = [];
            foreach ($value as $k => $v) {
                $lines[] = $isList
                    ? $pad . self::exportValue($v, $depth + 1)
                    : $pad . var_export((string) $k, true) . ' => ' . self::exportValue($v, $depth + 1);
            }
            return "[\n" . implode(",\n", $lines) . ",\n" . $padEnd . ']';
        }

        return var_export($value, true);
    }

    /**
     * @param array<string,mixed> $base
     * @param array<string,mixed> $override
     * @return array<string,mixed>
     */
    private static function mergeDeep(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && !array_is_list($value)) {
                $base[$key] = self::mergeDeep($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }
}
