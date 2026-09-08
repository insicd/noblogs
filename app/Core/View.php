<?php

declare(strict_types=1);

namespace Noblogs\Core;

/**
 * Template in PHP semplice, senza motore di templating.
 *
 * I template stanno in app/Views e ricevono le variabili come variabili locali.
 * Un template può dichiarare il proprio layout con $this->layout('...') e
 * riempire slot con $this->start('nome') / $this->end().
 */
final class View
{
    private static string $basePath = '';

    /** @var array<string,mixed> */
    private static array $shared = [];

    private ?string $layoutName = null;

    /** @var array<string,mixed> */
    private array $layoutData = [];

    /** @var array<string,string> */
    private array $slots = [];

    /** @var list<string> */
    private array $slotStack = [];

    public static function setBasePath(string $path): void
    {
        self::$basePath = rtrim($path, '/');
    }

    /** Variabili disponibili in ogni template (utente, tenant, impostazioni). */
    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    /** @return array<string,mixed> */
    public static function sharedData(): array
    {
        return self::$shared;
    }

    /** @param array<string,mixed> $data */
    public static function make(string $template, array $data = []): string
    {
        return (new self())->renderTemplate($template, $data);
    }

    /** @param array<string,mixed> $data */
    public static function response(string $template, array $data = [], int $status = 200): Response
    {
        return Response::html(self::make($template, $data), $status);
    }

    /** @param array<string,mixed> $data */
    private function renderTemplate(string $template, array $data): string
    {
        $content = $this->capture($template, $data);

        // I layout possono a loro volta dichiararne un altro (annidamento).
        while ($this->layoutName !== null) {
            $layout = $this->layoutName;
            $layoutData = $this->layoutData;
            $this->layoutName = null;
            $this->layoutData = [];

            // I template dei blog riempiono lo slot con start('content')/end()
            // e non scrivono nulla fuori: l'output catturato è vuoto. Se si
            // sovrascrivesse lo slot con quella stringa vuota, il guscio
            // mostrerebbe titolo e navigazione e niente altro.
            if (trim($content) !== '' || !$this->hasSlot('content')) {
                $this->slots['content'] = $content;
            }

            $content = $this->capture($layout, array_merge($data, $layoutData));
        }

        return $content;
    }

    /** @param array<string,mixed> $data */
    private function capture(string $template, array $data): string
    {
        $path = self::$basePath . '/' . str_replace(['..', '\\'], '', $template) . '.php';
        if (!is_file($path)) {
            throw new \RuntimeException("Template non trovato: $template");
        }

        $scope = array_merge(self::$shared, $data);
        extract($scope, EXTR_SKIP);

        ob_start();
        try {
            include $path;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }

    // -----------------------------------------------------------------------
    // API disponibile dentro i template
    // -----------------------------------------------------------------------

    /** @param array<string,mixed> $data */
    public function layout(string $name, array $data = []): void
    {
        $this->layoutName = $name;
        $this->layoutData = $data;
    }

    public function start(string $slot): void
    {
        $this->slotStack[] = $slot;
        ob_start();
    }

    public function end(): void
    {
        $slot = array_pop($this->slotStack);
        if ($slot === null) {
            throw new \LogicException('end() chiamato senza una start() corrispondente.');
        }
        $this->slots[$slot] = (string) ob_get_clean();
    }

    public function slot(string $name, string $default = ''): string
    {
        return $this->slots[$name] ?? $default;
    }

    public function hasSlot(string $name): bool
    {
        return isset($this->slots[$name]) && trim($this->slots[$name]) !== '';
    }

    /** @param array<string,mixed> $data */
    public function partial(string $template, array $data = []): void
    {
        echo $this->capture($template, $data);
    }
}
