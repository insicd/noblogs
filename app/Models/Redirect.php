<?php

declare(strict_types=1);

namespace Noblogs\Models;

use Noblogs\Core\Database;

/**
 * Reindirizzamenti definiti dall'autore, utili quando si arriva da un altro
 * sistema e si vuole non rompere i vecchi link.
 */
final class Redirect extends Model
{
    protected static string $table = 'redirects';

    protected static array $casts = [
        'id'          => 'int',
        'blog_id'     => 'int',
        'status_code' => 'int',
    ];

    public int $blog_id = 0;
    public string $from_path = '';
    public string $to_url = '';
    public int $status_code = 302;
    public string $created_at = '';

    public static function match(Blog $blog, string $path): ?self
    {
        $path = '/' . trim($path, '/');
        return self::hydrateOrNull(Database::instance()->fetch(
            'SELECT * FROM {{redirects}} WHERE blog_id = ? AND from_path = ?',
            [$blog->id, $path]
        ));
    }

    /** @return list<self> */
    public static function forBlog(Blog $blog): array
    {
        return self::hydrateAll(Database::instance()->fetchAll(
            'SELECT * FROM {{redirects}} WHERE blog_id = ? ORDER BY from_path ASC',
            [$blog->id]
        ));
    }

    /**
     * Sostituisce l'intero elenco con quello passato, nel formato
     * "/vecchio-percorso destinazione" una riga per redirect.
     */
    public static function replaceAll(Blog $blog, string $text): int
    {
        $entries = [];
        foreach (explode("\n", $text) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            $parts = preg_split('/\s+/', $line, 3);
            if ($parts === false || count($parts) < 2) {
                continue;
            }
            [$from, $to] = $parts;
            $code = isset($parts[2]) && in_array((int) $parts[2], [301, 302, 307, 308], true)
                ? (int) $parts[2]
                : 302;

            $from = '/' . trim($from, '/');
            if ($from === '/' || mb_strlen($from) > 200 || mb_strlen($to) > 400) {
                continue;
            }
            $entries[$from] = ['to' => $to, 'code' => $code];
        }

        Database::instance()->delete('redirects', 'blog_id = ?', [$blog->id]);
        foreach ($entries as $from => $entry) {
            Database::instance()->insert('redirects', [
                'blog_id'     => $blog->id,
                'from_path'   => $from,
                'to_url'      => $entry['to'],
                'status_code' => $entry['code'],
                'created_at'  => self::now(),
            ]);
        }

        return count($entries);
    }

    public static function asText(Blog $blog): string
    {
        $lines = [];
        foreach (self::forBlog($blog) as $redirect) {
            $lines[] = $redirect->from_path . ' ' . $redirect->to_url
                . ($redirect->status_code !== 302 ? ' ' . $redirect->status_code : '');
        }
        return implode("\n", $lines);
    }
}
