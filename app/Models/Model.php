<?php

declare(strict_types=1);

namespace Noblogs\Models;

use Noblogs\Core\Database;
use Noblogs\Support\Dates;

/**
 * Base dei modelli: idratazione da riga del database e persistenza.
 *
 * Le sottoclassi dichiarano il nome della tabella e l'elenco dei campi
 * scrivibili; i valori sono proprietà pubbliche tipizzate.
 */
abstract class Model
{
    public int $id = 0;

    /** Nome della tabella, senza prefisso. */
    protected static string $table = '';

    /**
     * Colonne che vengono lette e scritte. Le sottoclassi dichiarano il tipo
     * per la conversione: 'int', 'bool', 'float', 'json' o 'string'.
     *
     * @var array<string,string>
     */
    protected static array $casts = [];

    protected static function db(): Database
    {
        return Database::instance();
    }

    public static function table(): string
    {
        return static::$table;
    }

    /** @param array<string,mixed> $row */
    public static function hydrate(array $row): static
    {
        $model = new static();
        foreach ($row as $column => $value) {
            if (!property_exists($model, $column)) {
                continue;
            }
            $model->$column = self::cast($column, $value);
        }
        return $model;
    }

    /**
     * @param array<string,mixed>|null $row
     */
    protected static function hydrateOrNull(?array $row): ?static
    {
        return $row === null ? null : static::hydrate($row);
    }

    /**
     * @param list<array<string,mixed>> $rows
     * @return list<static>
     */
    protected static function hydrateAll(array $rows): array
    {
        return array_map(static fn(array $row): static => static::hydrate($row), $rows);
    }

    private static function cast(string $column, mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        return match (static::$casts[$column] ?? 'string') {
            'int'   => (int) $value,
            'bool'  => (bool) (int) $value,
            'float' => (float) $value,
            default => is_scalar($value) ? (string) $value : $value,
        };
    }

    public static function find(int $id): ?static
    {
        return static::hydrateOrNull(
            self::db()->fetch('SELECT * FROM {{' . static::$table . '}} WHERE id = ?', [$id])
        );
    }

    /** @param array<string,mixed> $data */
    protected function insertRow(array $data): int
    {
        $this->id = self::db()->insert(static::$table, $data);
        return $this->id;
    }

    /** @param array<string,mixed> $data */
    protected function updateRow(array $data): int
    {
        if ($this->id === 0) {
            throw new \LogicException('Impossibile aggiornare un record senza id.');
        }
        return self::db()->update(static::$table, $data, 'id = :model_id', ['model_id' => $this->id]);
    }

    public function delete(): bool
    {
        if ($this->id === 0) {
            return false;
        }
        return self::db()->delete(static::$table, 'id = ?', [$this->id]) > 0;
    }

    public function exists(): bool
    {
        return $this->id > 0;
    }

    protected static function now(): string
    {
        return Dates::nowString();
    }

    /**
     * @param list<string>|string|null $value
     */
    protected static function encodeList(array|string|null $value): string
    {
        if (is_string($value)) {
            return $value;
        }
        return json_encode(array_values($value ?? []), JSON_UNESCAPED_UNICODE) ?: '[]';
    }

    /** @return list<string> */
    protected static function decodeList(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        $decoded = json_decode($value, true);
        return is_array($decoded) ? array_values(array_filter(array_map('strval', $decoded), 'strlen')) : [];
    }
}
