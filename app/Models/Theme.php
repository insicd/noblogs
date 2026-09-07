<?php

declare(strict_types=1);

namespace Noblogs\Models;

use Noblogs\Core\Database;

/**
 * Temi disponibili nella galleria.
 *
 * Il CSS viene incorporato nella pagina invece di essere servito come file
 * separato: una richiesta HTTP in meno e nessun asset da mettere in cache.
 */
final class Theme extends Model
{
    protected static string $table = 'themes';

    protected static array $casts = [
        'id'         => 'int',
        'sort_order' => 'int',
    ];

    public string $slug = '';
    public string $title = '';
    public ?string $description = null;
    public ?string $css = null;
    public int $sort_order = 0;

    /** @var array<string,self>|null */
    private static ?array $cache = null;

    /** @return array<string,self> */
    public static function all(): array
    {
        if (self::$cache !== null) {
            return self::$cache;
        }

        $themes = [];
        foreach (Database::instance()->fetchAll('SELECT * FROM {{themes}} ORDER BY sort_order ASC, title ASC') as $row) {
            $theme = self::hydrate($row);
            $themes[$theme->slug] = $theme;
        }
        return self::$cache = $themes;
    }

    public static function findBySlug(string $slug): ?self
    {
        return self::all()[$slug] ?? null;
    }

    public static function hasSlug(string $slug): bool
    {
        return isset(self::all()[$slug]);
    }

    /**
     * CSS effettivo di un blog: tema scelto più CSS personale, oppure solo
     * quest'ultimo se l'autore ha chiesto di sostituire tutto.
     */
    public static function stylesheetFor(Blog $blog): string
    {
        $custom = trim($blog->custom_css ?? '');

        if ($blog->overwrite_styles) {
            return self::sanitize($custom);
        }

        $theme = self::findBySlug($blog->theme) ?? self::findBySlug('default');
        $base = trim($theme?->css ?? '');

        return self::sanitize($base . ($custom !== '' ? "\n\n/* --- */\n" . $custom : ''));
    }

    /**
     * Un </style> nel CSS chiuderebbe il blocco e permetterebbe di iniettare
     * markup arbitrario nella pagina: si tronca al primo tentativo.
     */
    public static function sanitize(string $css): string
    {
        $position = stripos($css, '</style');
        if ($position !== false) {
            $css = substr($css, 0, $position);
        }
        return $css;
    }

    /**
     * Allinea la tabella dei temi al file di definizione: aggiunge i nuovi,
     * aggiorna quelli esistenti, non tocca quelli creati a mano dall'admin.
     */
    public static function sync(): int
    {
        $definitions = require NOBLOGS_ROOT . '/db/themes.php';
        $count = 0;

        foreach ($definitions as $slug => $definition) {
            $existing = Database::instance()->fetch('SELECT id FROM {{themes}} WHERE slug = ?', [$slug]);
            $payload = [
                'title'       => $definition['title'],
                'description' => $definition['description'] ?? null,
                'css'         => $definition['css'],
                'sort_order'  => $definition['sort_order'] ?? 0,
            ];

            if ($existing === null) {
                Database::instance()->insert('themes', $payload + ['slug' => $slug]);
            } else {
                Database::instance()->update('themes', $payload, 'id = :theme_id', ['theme_id' => $existing['id']]);
            }
            $count++;
        }

        self::$cache = null;
        return $count;
    }
}
