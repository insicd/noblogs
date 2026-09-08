<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Admin;

use Noblogs\Core\Database;
use Noblogs\Core\Response;
use Noblogs\Core\Url;
use Noblogs\Models\Blog;

/**
 * Elenco dei blog e azioni di moderazione su un singolo blog.
 */
final class BlogController extends AdminController
{
    /** Azioni ammesse su un blog. */
    private const ACTIONS = [
        'approva', 'nascondi', 'mostra', 'segnala', 'rimuovi-segnalazione',
        'consenti-html', 'revoca-html', 'elimina',
    ];

    /** Stati su cui si può filtrare l'elenco. */
    private const STATES = ['tutti', 'attesa', 'approvati', 'nascosti', 'segnalati'];

    /** Criteri di ordinamento, tradotti in ORDER BY. */
    private const SORTS = [
        'recenti'   => 'b.created_at DESC',
        'attivita'  => 'b.last_posted_at DESC',
        'rischio'   => 'b.dodginess_score DESC, b.created_at ASC',
        'spazio'    => 'b.storage_used DESC',
        'alfabetico' => 'b.subdomain ASC',
    ];

    public function index(): Response
    {
        if ($denied = $this->guard()) {
            return $denied;
        }

        $search = mb_substr($this->request->trimmed('q'), 0, 100);
        $state = $this->request->trimmed('stato', 'tutti');
        $sort = $this->request->trimmed('ordina', 'recenti');

        if (!in_array($state, self::STATES, true)) {
            $state = 'tutti';
        }
        if (!array_key_exists($sort, self::SORTS)) {
            $sort = 'recenti';
        }

        [$where, $params] = $this->filters($search, $state);
        $page = $this->pageNumber();
        $offset = ($page - 1) * self::PER_PAGE;

        $db = Database::instance();
        $total = (int) $db->fetchColumn(
            "SELECT COUNT(*) FROM {{blogs}} b INNER JOIN {{users}} u ON u.id = b.user_id WHERE $where",
            $params
        );

        $rows = $db->fetchAll(
            "SELECT b.*, u.email AS owner_email, u.is_active AS owner_active,
                    (SELECT COUNT(*) FROM {{posts}} p WHERE p.blog_id = b.id AND p.is_page = 0) AS post_count
             FROM {{blogs}} b
             INNER JOIN {{users}} u ON u.id = b.user_id
             WHERE $where
             ORDER BY " . self::SORTS[$sort] . '
             LIMIT ' . self::PER_PAGE . ' OFFSET ' . $offset,
            $params
        );

        $blogs = [];
        foreach ($rows as $row) {
            $blogs[] = [
                'blog'         => Blog::hydrate($row),
                'owner_email'  => (string) $row['owner_email'],
                'owner_active' => (bool) (int) $row['owner_active'],
                'posts'        => (int) $row['post_count'],
            ];
        }

        return $this->panel('admin/blogs', [
            'activeNav' => 'blogs',
            'blogs'     => $blogs,
            'search'    => $search,
            'state'     => $state,
            'sort'      => $sort,
            'total'     => $total,
            'page'      => $page,
            'pages'     => max(1, (int) ceil($total / self::PER_PAGE)),
        ]);
    }

    /**
     * Esegue un'azione di moderazione.
     *
     * L'eliminazione non avviene al primo invio: se manca la conferma esatta
     * si restituisce la schermata che elenca cosa sparirebbe.
     */
    public function action(string $id): Response
    {
        if ($denied = $this->guard()) {
            return $denied;
        }
        if ($response = $this->requireCsrf()) {
            return $response;
        }

        $blog = Blog::find((int) $id);
        if ($blog === null) {
            $this->flash('error', __('admin.blogs.error.not_found'));
            return $this->redirect(Url::to('/admin/blog'));
        }

        $action = $this->request->trimmed('azione');
        if (!in_array($action, self::ACTIONS, true)) {
            $this->flash('error', __('admin.error.unknown_action'));
            return $this->back(Url::to('/admin/blog'));
        }

        $note = $this->note();

        if ($action === 'elimina') {
            return $this->destroy($blog, $note);
        }

        $wasPending = !$blog->reviewed;

        // La nota del moderatore resta attaccata al blog solo per le decisioni
        // che l'autore deve poter capire; le altre finiscono solo nel registro.
        $reviewerNote = $note !== '' ? $note : null;

        $blog->update(match ($action) {
            'approva'              => ['reviewed' => true, 'to_review' => false, 'reviewer_note' => $reviewerNote],
            'nascondi'             => ['hidden' => true, 'reviewer_note' => $reviewerNote],
            'mostra'               => ['hidden' => false],
            'segnala'              => ['flagged' => true, 'to_review' => true, 'reviewer_note' => $reviewerNote],
            'rimuovi-segnalazione' => ['flagged' => false, 'to_review' => false],
            'consenti-html'        => ['allow_raw_html' => true],
            'revoca-html'          => ['allow_raw_html' => false],
            default                => [],
        });

        if ($action === 'approva' && $wasPending) {
            $blog->notifyApproved($blog->owner());
        }

        $this->record($blog->id, $action, $note);

        $subdomain = $blog->subdomain;
        $this->flash('success', match ($action) {
            'approva'              => __('admin.blogs.done.approva', ['blog' => $subdomain]),
            'nascondi'             => __('admin.blogs.done.nascondi', ['blog' => $subdomain]),
            'mostra'               => __('admin.blogs.done.mostra', ['blog' => $subdomain]),
            'segnala'              => __('admin.blogs.done.segnala', ['blog' => $subdomain]),
            'rimuovi-segnalazione' => __('admin.blogs.done.rimuovi_segnalazione', ['blog' => $subdomain]),
            'consenti-html'        => __('admin.blogs.done.consenti_html', ['blog' => $subdomain]),
            'revoca-html'          => __('admin.blogs.done.revoca_html', ['blog' => $subdomain]),
            default                => __('admin.blogs.done.generic', ['blog' => $subdomain]),
        });

        return $this->back(Url::to('/admin/blog'));
    }

    /**
     * Eliminazione definitiva, in due tempi: prima si mostra cosa si perde,
     * poi si chiede di ridigitare il sottodominio.
     */
    private function destroy(Blog $blog, string $note): Response
    {
        $confirmation = $this->request->trimmed('conferma');
        $footprint = self::blogFootprint($blog);

        if ($confirmation !== $blog->subdomain) {
            return $this->panel('admin/blog-delete', [
                'activeNav' => 'blogs',
                'blog'      => $blog,
                'owner'     => $blog->owner(),
                'footprint' => $footprint,
                'note'      => $note,
                'mismatch'  => $confirmation !== '',
            ]);
        }

        $subdomain = $blog->subdomain;

        // La riga sparisce con l'eliminazione: il registro conserva cosa c'era.
        $this->record(null, 'elimina-blog', trim(sprintf(
            '%s — articoli: %d, file: %d, iscritti: %d%s',
            $subdomain,
            $footprint['posts'] + $footprint['pages'],
            $footprint['files'],
            $footprint['subscribers'],
            $note !== '' ? ' — ' . $note : ''
        )));

        self::purgeBlog($blog);

        $this->flash('success', __('admin.blogs.delete.done', ['blog' => $subdomain]));
        return $this->redirect(Url::to('/admin/blog'));
    }

    /**
     * Costruisce la clausola WHERE di ricerca e filtro.
     *
     * @return array{0:string,1:array<string,mixed>}
     */
    private function filters(string $search, string $state): array
    {
        $conditions = ['1 = 1'];
        $params = [];

        if ($search !== '') {
            // Con le prepared statement vere lo stesso segnaposto non può
            // comparire più volte: ne serve uno per colonna.
            $conditions[] = '(b.subdomain LIKE :q1 OR b.title LIKE :q2 OR b.domain LIKE :q3 OR u.email LIKE :q4)';
            $like = '%' . self::escapeLike($search) . '%';
            $params = ['q1' => $like, 'q2' => $like, 'q3' => $like, 'q4' => $like];
        }

        $conditions[] = match ($state) {
            'attesa'     => '(b.to_review = 1 OR b.reviewed = 0)',
            'approvati'  => '(b.reviewed = 1 AND b.hidden = 0)',
            'nascosti'   => 'b.hidden = 1',
            'segnalati'  => 'b.flagged = 1',
            default      => '1 = 1',
        };

        return [implode(' AND ', $conditions), $params];
    }

    private static function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
