<?php

declare(strict_types=1);

namespace Noblogs\Markdown;

/**
 * Cache su file dell'HTML già reso.
 *
 * Su hosting condiviso non si può contare su Redis o su APCu: il filesystem è
 * l'unico deposito sempre disponibile. Le voci scadono da sole e vengono
 * invalidate in blocco quando un blog cambia.
 */
final class Cache
{
    private static ?bool $writable = null;

    public static function get(string $key): ?string
    {
        if (!self::isWritable()) {
            return null;
        }

        $path = self::path($key);
        if (!is_file($path)) {
            return null;
        }

        $contents = @file_get_contents($path);
        if ($contents === false || strlen($contents) < 11) {
            return null;
        }

        $expiry = (int) substr($contents, 0, 10);
        if ($expiry !== 0 && $expiry < time()) {
            @unlink($path);
            return null;
        }

        return substr($contents, 11);
    }

    public static function put(string $key, string $value, int $ttlSeconds = 3600): void
    {
        if (!self::isWritable()) {
            return;
        }

        $path = self::path($key);
        $directory = dirname($path);
        if (!is_dir($directory) && !@mkdir($directory, 0755, true) && !is_dir($directory)) {
            return;
        }

        $expiry = $ttlSeconds > 0 ? time() + $ttlSeconds : 0;
        // Scrittura atomica: un file parziale letto da un'altra richiesta
        // produrrebbe una pagina troncata.
        $temporary = $path . '.' . getmypid() . '.tmp';
        if (@file_put_contents($temporary, sprintf('%010d|%s', $expiry, $value), LOCK_EX) !== false) {
            @rename($temporary, $path);
        }
    }

    public static function forget(string $key): void
    {
        $path = self::path($key);
        if (is_file($path)) {
            @unlink($path);
        }
    }

    /** Svuota la cache di un intero blog, dopo una modifica ai contenuti. */
    public static function flushBlog(int $blogId): void
    {
        self::removeDirectory(NOBLOGS_STORAGE . '/cache/render/' . $blogId);
    }

    public static function flushAll(): void
    {
        self::removeDirectory(NOBLOGS_STORAGE . '/cache/render');
    }

    /** Elimina le voci scadute; da richiamare da un'attività pianificata. */
    public static function prune(): int
    {
        $base = NOBLOGS_STORAGE . '/cache/render';
        if (!is_dir($base)) {
            return 0;
        }

        $removed = 0;
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($base, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo || !$item->isFile()) {
                continue;
            }
            $handle = @fopen($item->getPathname(), 'rb');
            if ($handle === false) {
                continue;
            }
            $expiry = (int) fread($handle, 10);
            fclose($handle);

            if ($expiry !== 0 && $expiry < time()) {
                @unlink($item->getPathname());
                $removed++;
            }
        }

        return $removed;
    }

    private static function path(string $key): string
    {
        $safe = preg_replace('#[^a-z0-9/_-]#i', '', $key) ?? 'invalid';
        return NOBLOGS_STORAGE . '/cache/' . $safe . '.cache';
    }

    private static function isWritable(): bool
    {
        if (self::$writable !== null) {
            return self::$writable;
        }
        $base = NOBLOGS_STORAGE . '/cache';
        if (!is_dir($base)) {
            @mkdir($base, 0755, true);
        }
        return self::$writable = is_dir($base) && is_writable($base);
    }

    private static function removeDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }

        @rmdir($directory);
    }
}
