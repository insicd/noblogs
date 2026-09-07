<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Site;

use Noblogs\Core\Database;
use Noblogs\Core\Response;
use Noblogs\Models\Post;

/**
 * Ricerca dentro un singolo blog.
 *
 * Ricerca testuale semplice su titolo e contenuto: su una base di qualche
 * migliaio di articoli un LIKE è più che sufficiente, e non richiede indici
 * full-text che non tutti gli hosting condivisi hanno configurato.
 */
final class SearchController extends SiteController
{
    private const MAX_RESULTS = 50;

    public function index(): Response
    {
        $query = trim($this->request->query('q') ?? '');
        $results = [];

        if (mb_strlen($query) >= 2) {
            $results = $this->search($query);
        }

        return $this->page('site/search', [
            'bodyClass' => 'search',
            'query'     => $query,
            'results'   => $results,
            'pageTitle' => ($query !== '' ? __('search.results_for', ['query' => $query]) : __('search.title'))
                . ' — ' . $this->blog->title,
            // Le pagine di ricerca sono infinite e senza contenuto proprio.
            'indexable' => false,
            'trackPath' => null,
        ]);
    }

    /** @return list<Post> */
    private function search(string $query): array
    {
        // Ogni parola deve comparire da qualche parte: la ricerca è in AND,
        // che è il comportamento che le persone si aspettano.
        $terms = array_slice(array_filter(
            preg_split('/\s+/u', $query) ?: [],
            static fn(string $term): bool => mb_strlen($term) >= 2
        ), 0, 6);

        if ($terms === []) {
            return [];
        }

        $conditions = [];
        $params = ['blog_id' => $this->blog->id];

        foreach (array_values($terms) as $index => $term) {
            $conditions[] = "(title LIKE :term$index OR content LIKE :term$index OR tags LIKE :term$index)";
            $params["term$index"] = '%' . self::escapeLike($term) . '%';
        }

        $rows = Database::instance()->fetchAll(
            'SELECT * FROM {{posts}}
             WHERE blog_id = :blog_id
               AND is_published = 1 AND hidden = 0
               AND published_at <= UTC_TIMESTAMP()
               AND ' . implode(' AND ', $conditions) . '
             ORDER BY published_at DESC
             LIMIT ' . self::MAX_RESULTS,
            $params
        );

        return array_map(static fn(array $row): Post => Post::hydrate($row), $rows);
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
