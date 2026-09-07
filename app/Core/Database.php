<?php

declare(strict_types=1);

namespace Noblogs\Core;

use PDO;
use PDOStatement;

/**
 * Wrapper sottile su PDO.
 *
 * Nelle query i nomi di tabella si scrivono tra doppie graffe ({{posts}}) e
 * vengono espansi con il prefisso configurato, così la stessa installazione può
 * convivere con altre applicazioni in un unico database condiviso.
 */
final class Database
{
    private static ?self $instance = null;

    private PDO $pdo;

    private string $prefix;

    private function __construct(PDO $pdo, string $prefix)
    {
        $this->pdo = $pdo;
        $this->prefix = $prefix;
    }

    public static function instance(): self
    {
        if (self::$instance === null) {
            self::$instance = self::connectFromConfig();
        }
        return self::$instance;
    }

    public static function connectFromConfig(): self
    {
        return self::connect([
            'host'     => (string) Config::get('db.host', 'localhost'),
            'port'     => (int) Config::get('db.port', 3306),
            'name'     => (string) Config::get('db.name', ''),
            'user'     => (string) Config::get('db.user', ''),
            'password' => (string) Config::get('db.password', ''),
            'prefix'   => (string) Config::get('db.prefix', ''),
            'socket'   => Config::get('db.socket'),
        ]);
    }

    /**
     * @param array{host:string,port:int,name:string,user:string,password:string,prefix?:string,socket?:?string} $params
     */
    public static function connect(array $params): self
    {
        $dsn = isset($params['socket']) && $params['socket']
            ? sprintf('mysql:unix_socket=%s;dbname=%s;charset=utf8mb4', $params['socket'], $params['name'])
            : sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $params['host'], $params['port'], $params['name']);

        $pdo = new PDO($dsn, $params['user'], $params['password'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
            PDO::ATTR_STRINGIFY_FETCHES  => false,
        ]);
        // Le date sono sempre in UTC; la conversione al fuso dell'utente
        // avviene solo in fase di visualizzazione.
        $pdo->exec("SET time_zone = '+00:00'");

        return new self($pdo, $params['prefix'] ?? '');
    }

    public static function setInstance(self $db): void
    {
        self::$instance = $db;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function prefix(): string
    {
        return $this->prefix;
    }

    public function table(string $name): string
    {
        return $this->prefix . $name;
    }

    public function expand(string $sql): string
    {
        if (!str_contains($sql, '{{')) {
            return $sql;
        }
        return preg_replace_callback(
            '/\{\{(\w+)\}\}/',
            fn(array $m): string => '`' . $this->prefix . $m[1] . '`',
            $sql
        ) ?? $sql;
    }

    /** @param array<string|int,mixed> $params */
    public function query(string $sql, array $params = []): PDOStatement
    {
        $statement = $this->pdo->prepare($this->expand($sql));
        $statement->execute($params);
        return $statement;
    }

    /**
     * @param array<string|int,mixed> $params
     * @return array<string,mixed>|null
     */
    public function fetch(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    /**
     * @param array<string|int,mixed> $params
     * @return list<array<string,mixed>>
     */
    public function fetchAll(string $sql, array $params = []): array
    {
        return $this->query($sql, $params)->fetchAll();
    }

    /** @param array<string|int,mixed> $params */
    public function fetchColumn(string $sql, array $params = [], int $column = 0): mixed
    {
        $value = $this->query($sql, $params)->fetchColumn($column);
        return $value === false ? null : $value;
    }

    /** @param array<string,mixed> $data */
    public function insert(string $table, array $data): int
    {
        $columns = array_keys($data);
        $sql = sprintf(
            'INSERT INTO {{%s}} (%s) VALUES (%s)',
            $table,
            implode(', ', array_map(static fn(string $c): string => "`$c`", $columns)),
            implode(', ', array_map(static fn(string $c): string => ":$c", $columns))
        );
        $this->query($sql, $data);
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param array<string,mixed> $data
     * @param array<string,mixed> $params
     */
    public function update(string $table, array $data, string $where, array $params = []): int
    {
        $assignments = [];
        $bindings = [];
        foreach ($data as $column => $value) {
            $assignments[] = "`$column` = :set_$column";
            $bindings["set_$column"] = $value;
        }
        $sql = sprintf('UPDATE {{%s}} SET %s WHERE %s', $table, implode(', ', $assignments), $where);
        return $this->query($sql, $bindings + $params)->rowCount();
    }

    /** @param array<string,mixed> $params */
    public function delete(string $table, string $where, array $params = []): int
    {
        return $this->query(sprintf('DELETE FROM {{%s}} WHERE %s', $table, $where), $params)->rowCount();
    }

    public function transaction(callable $callback): mixed
    {
        $this->pdo->beginTransaction();
        try {
            $result = $callback($this);
            $this->pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    public function tableExists(string $table): bool
    {
        $stmt = $this->pdo->prepare('SHOW TABLES LIKE ?');
        $stmt->execute([$this->table($table)]);
        return $stmt->fetchColumn() !== false;
    }
}
