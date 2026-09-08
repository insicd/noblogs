<?php

declare(strict_types=1);

namespace Noblogs\Core;

/**
 * Rappresentazione immutabile della richiesta HTTP in ingresso.
 */
final class Request
{
    public readonly string $method;
    public readonly string $host;
    public readonly string $path;
    public readonly bool $secure;

    /** @var array<string,mixed> */
    public readonly array $query;

    /** @var array<string,mixed> */
    public readonly array $post;

    /** @var array<string,mixed> */
    public readonly array $files;

    /** @var array<string,string> */
    public readonly array $cookies;

    /**
     * Segmenti del path già decodificati, senza elementi vuoti.
     *
     * @var list<string>
     */
    public readonly array $segments;

    /**
     * @param array<string,mixed>  $query
     * @param array<string,mixed>  $post
     * @param array<string,mixed>  $files
     * @param array<string,string> $cookies
     */
    public function __construct(
        string $method,
        string $host,
        string $path,
        bool $secure,
        array $query = [],
        array $post = [],
        array $files = [],
        array $cookies = []
    ) {
        $this->method = strtoupper($method);
        $this->host = strtolower($host);
        $this->path = $path;
        $this->secure = $secure;
        $this->query = $query;
        $this->post = $post;
        $this->files = $files;
        $this->cookies = $cookies;
        $this->segments = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));
    }

    public static function capture(): self
    {
        $host = $_SERVER['HTTP_HOST'] ?? $_SERVER['SERVER_NAME'] ?? 'localhost';
        // Scarta la porta: la logica di routing ragiona solo sull'hostname.
        $host = preg_replace('/:\d+$/', '', (string) $host) ?? 'localhost';

        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        $path = is_string($path) ? rawurldecode($path) : '/';

        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || ($_SERVER['SERVER_PORT'] ?? null) == 443
            || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';

        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $post = $_POST;
        // Alcuni hosting (CGI/FastCGI) lasciano $_POST vuoto: il corpo c'è
        // comunque in php://input e senza questo i voti e gli hit non arrivano.
        if ($post === [] && $method === 'POST') {
            $type = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
            if ($type === '' || str_contains($type, 'application/x-www-form-urlencoded')) {
                $raw = file_get_contents('php://input');
                if (is_string($raw) && $raw !== '') {
                    $parsed = [];
                    parse_str($raw, $parsed);
                    if (is_array($parsed) && $parsed !== []) {
                        $post = $parsed;
                    }
                }
            }
        }

        return new self(
            $method,
            $host,
            $path,
            $secure,
            $_GET,
            $post,
            $_FILES,
            array_map('strval', $_COOKIE)
        );
    }

    /**
     * Copia della richiesta con un path diverso: serve al router quando il
     * prefisso /nomeblog/ viene consumato dalla risoluzione del tenant.
     */
    public function withPath(string $path): self
    {
        return new self(
            $this->method,
            $this->host,
            $path,
            $this->secure,
            $this->query,
            $this->post,
            $this->files,
            $this->cookies
        );
    }

    public function isGet(): bool
    {
        return $this->method === 'GET' || $this->method === 'HEAD';
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }

    public function query(string $key, ?string $default = null): ?string
    {
        $value = $this->query[$key] ?? null;
        return is_scalar($value) ? (string) $value : $default;
    }

    public function hasQuery(string $key): bool
    {
        return array_key_exists($key, $this->query);
    }

    public function input(string $key, ?string $default = null): ?string
    {
        $value = $this->post[$key] ?? $this->query[$key] ?? null;
        if (!is_scalar($value)) {
            return $default;
        }
        // I browser inviano CRLF nelle textarea: normalizziamo subito, così il
        // markdown salvato è sempre a interruzioni di riga singole.
        return str_replace("\r\n", "\n", (string) $value);
    }

    public function trimmed(string $key, string $default = ''): string
    {
        return trim($this->input($key, $default) ?? $default);
    }

    public function boolean(string $key): bool
    {
        $value = $this->post[$key] ?? $this->query[$key] ?? null;
        if ($value === null) {
            return false;
        }
        return !in_array(strtolower((string) $value), ['', '0', 'false', 'no', 'off'], true);
    }

    public function int(string $key, int $default = 0): int
    {
        $value = $this->input($key);
        return $value !== null && is_numeric($value) ? (int) $value : $default;
    }

    /** @return list<string> */
    public function list(string $key): array
    {
        $value = $this->post[$key] ?? $this->query[$key] ?? [];
        return is_array($value) ? array_values(array_map('strval', $value)) : [];
    }

    public function cookie(string $key, ?string $default = null): ?string
    {
        return $this->cookies[$key] ?? $default;
    }

    public function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        $value = $_SERVER[$key] ?? null;
        return is_string($value) ? $value : null;
    }

    public function userAgent(): string
    {
        return (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    }

    public function referrer(): string
    {
        return (string) ($_SERVER['HTTP_REFERER'] ?? '');
    }

    public function isAjax(): bool
    {
        return strtolower($this->header('X-Requested-With') ?? '') === 'xmlhttprequest';
    }

    /**
     * IP del client. Gli header dei proxy sono considerati solo se la richiesta
     * arriva davvero da un proxy noto, per non consentire lo spoofing dell'IP
     * (che qui determina l'identità anonima nelle statistiche e negli upvote).
     */
    public function ip(): string
    {
        $remote = (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');

        $trusted = (array) Config::get('security.trusted_proxies', []);
        if ($trusted !== [] && in_array($remote, $trusted, true)) {
            $forwarded = $_SERVER['HTTP_CF_CONNECTING_IP']
                ?? $_SERVER['HTTP_X_REAL_IP']
                ?? null;
            if (!$forwarded && isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $parts = explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']);
                $forwarded = trim($parts[0]);
            }
            if (is_string($forwarded) && filter_var($forwarded, FILTER_VALIDATE_IP)) {
                return $forwarded;
            }
        }

        return $remote;
    }

    public function scheme(): string
    {
        return $this->secure ? 'https' : 'http';
    }

    public function url(): string
    {
        return $this->scheme() . '://' . $this->host . $this->path;
    }
}
