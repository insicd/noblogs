<?php

declare(strict_types=1);

namespace Noblogs\Core;

/**
 * Risposta HTTP. Viene costruita dai controller e inviata dal front controller.
 */
final class Response
{
    private int $status = 200;

    /** @var array<string,string> */
    private array $headers = [];

    /** @var list<array{name:string,value:string,options:array<string,mixed>}> */
    private array $cookies = [];

    private string $body = '';

    public function __construct(string $body = '', int $status = 200, array $headers = [])
    {
        $this->body = $body;
        $this->status = $status;
        foreach ($headers as $name => $value) {
            $this->headers[strtolower((string) $name)] = (string) $value;
        }
    }

    public static function html(string $body, int $status = 200): self
    {
        return (new self($body, $status))->withHeader('Content-Type', 'text/html; charset=UTF-8');
    }

    public static function text(string $body, int $status = 200): self
    {
        return (new self($body, $status))->withHeader('Content-Type', 'text/plain; charset=UTF-8');
    }

    public static function xml(string $body, int $status = 200): self
    {
        return (new self($body, $status))->withHeader('Content-Type', 'application/xml; charset=UTF-8');
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $encoded = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        return (new self($encoded === false ? '{}' : $encoded, $status))
            ->withHeader('Content-Type', 'application/json; charset=UTF-8');
    }

    public static function redirect(string $location, int $status = 302): self
    {
        return (new self('', $status))->withHeader('Location', $location);
    }

    public static function noContent(int $status = 204): self
    {
        return new self('', $status);
    }

    public static function download(string $body, string $filename, string $mime = 'application/octet-stream'): self
    {
        $safe = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?? 'download';
        return (new self($body))
            ->withHeader('Content-Type', $mime)
            ->withHeader('Content-Disposition', 'attachment; filename="' . $safe . '"');
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[strtolower($name)] = $value;
        return $this;
    }

    public function withStatus(int $status): self
    {
        $this->status = $status;
        return $this;
    }

    /** @param array<string,mixed> $options */
    public function withCookie(string $name, string $value, array $options = []): self
    {
        $this->cookies[] = ['name' => $name, 'value' => $value, 'options' => $options];
        return $this;
    }

    /**
     * Cache sul CDN ma mai nel browser: le pagine restano aggiornabili con una
     * purge, e un lettore che torna sul sito vede sempre l'ultima versione.
     */
    public function cachePublic(int $seconds, ?string $tag = null): self
    {
        $this->withHeader('Cache-Control', 'public, s-maxage=' . $seconds . ', max-age=0');
        if ($tag !== null) {
            $this->withHeader('Cache-Tag', $tag);
        }
        return $this;
    }

    public function noCache(): self
    {
        return $this
            ->withHeader('Cache-Control', 'private, no-store, no-cache, max-age=0, must-revalidate')
            ->withHeader('Pragma', 'no-cache');
    }

    public function noIndex(): self
    {
        return $this->withHeader('X-Robots-Tag', 'noindex, nofollow');
    }

    public function status(): int
    {
        return $this->status;
    }

    public function body(): string
    {
        return $this->body;
    }

    /** @return array<string,string> */
    public function headers(): array
    {
        return $this->headers;
    }

    public function send(bool $headOnly = false): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($this->normalizeHeaderName($name) . ': ' . $value);
            }
            foreach ($this->cookies as $cookie) {
                setcookie($cookie['name'], $cookie['value'], $cookie['options']);
            }
        }
        if (!$headOnly) {
            echo $this->body;
        }
    }

    private function normalizeHeaderName(string $name): string
    {
        return implode('-', array_map('ucfirst', explode('-', $name)));
    }
}
