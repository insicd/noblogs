<?php

declare(strict_types=1);

namespace Noblogs\Core;

/**
 * Gestione centralizzata degli errori.
 *
 * In produzione l'utente vede una pagina generica e il dettaglio finisce in
 * storage/logs/error.log; in debug il dettaglio è mostrato a schermo.
 */
final class ErrorHandler
{
    private static bool $debug = false;

    public static function register(bool $debug): void
    {
        self::$debug = $debug;

        error_reporting(E_ALL);
        ini_set('display_errors', $debug ? '1' : '0');
        ini_set('log_errors', '1');

        $logFile = NOBLOGS_STORAGE . '/logs/error.log';
        if (is_dir(dirname($logFile))) {
            ini_set('error_log', $logFile);
        }

        set_exception_handler([self::class, 'handleException']);
        set_error_handler([self::class, 'handleError']);
        register_shutdown_function([self::class, 'handleShutdown']);
    }

    public static function handleError(int $severity, string $message, string $file = '', int $line = 0): bool
    {
        if (!(error_reporting() & $severity)) {
            return false;
        }
        throw new \ErrorException($message, 0, $severity, $file, $line);
    }

    public static function handleException(\Throwable $e): void
    {
        self::log($e);

        if (headers_sent()) {
            return;
        }

        http_response_code(500);
        header('Content-Type: text/html; charset=UTF-8');

        if (self::$debug) {
            echo self::renderDebug($e);
            return;
        }

        echo self::renderGeneric();
    }

    public static function handleShutdown(): void
    {
        $error = error_get_last();
        if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
            return;
        }
        self::handleException(new \ErrorException(
            $error['message'],
            0,
            $error['type'],
            $error['file'],
            $error['line']
        ));
    }

    public static function log(\Throwable $e): void
    {
        error_log(sprintf(
            "%s: %s in %s:%d\n%s",
            $e::class,
            $e->getMessage(),
            $e->getFile(),
            $e->getLine(),
            $e->getTraceAsString()
        ));
    }

    private static function renderDebug(\Throwable $e): string
    {
        return '<!doctype html><meta charset="utf-8"><title>Errore</title>'
            . '<style>body{font:14px/1.6 ui-monospace,monospace;margin:2rem;max-width:60rem}'
            . 'h1{font-size:1.2rem;color:#b00}pre{background:#f5f5f5;padding:1rem;overflow:auto}</style>'
            . '<h1>' . e($e::class) . '</h1>'
            . '<p><strong>' . e($e->getMessage()) . '</strong></p>'
            . '<p>' . e($e->getFile()) . ':' . $e->getLine() . '</p>'
            . '<pre>' . e($e->getTraceAsString()) . '</pre>';
    }

    private static function renderGeneric(): string
    {
        return '<!doctype html><meta charset="utf-8"><title>Errore del server</title>'
            . '<style>body{font:16px/1.6 system-ui,sans-serif;margin:0;display:grid;'
            . 'place-items:center;min-height:100vh;text-align:center;color:#333}</style>'
            . '<div><h1>Qualcosa è andato storto</h1>'
            . '<p>Il server ha incontrato un errore imprevisto. Riprova tra poco.</p></div>';
    }
}
