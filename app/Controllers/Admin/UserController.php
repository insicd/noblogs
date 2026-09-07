<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Admin;

use Noblogs\Core\Database;
use Noblogs\Core\Response;
use Noblogs\Core\Url;
use Noblogs\Models\Blog;
use Noblogs\Models\User;

/**
 * Elenco degli utenti e azioni sul singolo account.
 */
final class UserController extends AdminController
{
    /** Azioni ammesse su un utente. */
    private const ACTIONS = [
        'sospendi', 'riattiva', 'verifica-email', 'promuovi-moderatore',
        'promuovi-admin', 'revoca-ruolo', 'cambia-limite-blog', 'elimina',
    ];

    /**
     * Azioni riservate al ruolo 'admin'.
     *
     * Un moderatore modera i contenuti; distribuire i poteri e cancellare
     * account è un'altra cosa e resta a chi amministra.
     */
    private const ADMIN_ONLY = [
        'promuovi-moderatore', 'promuovi-admin', 'revoca-ruolo', 'elimina',
    ];

    private const STATES = ['tutti', 'attivi', 'sospesi', 'non-verificati', 'staff'];

    private const SORTS = [
        'recenti'  => 'u.created_at DESC',
        'accesso'  => 'u.last_login_at DESC',
        'email'    => 'u.email ASC',
        'blog'     => 'blog_count DESC, u.created_at DESC',
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
        $total = (int) $db->fetchColumn("SELECT COUNT(*) FROM {{users}} u WHERE $where", $params);

        $rows = $db->fetchAll(
            "SELECT u.*, (SELECT COUNT(*) FROM {{blogs}} b WHERE b.user_id = u.id) AS blog_count
             FROM {{users}} u
             WHERE $where
             ORDER BY " . self::SORTS[$sort] . '
             LIMIT ' . self::PER_PAGE . ' OFFSET ' . $offset,
            $params
        );

        $users = [];
        foreach ($rows as $row) {
            $users[] = [
                'user'  => User::hydrate($row),
                'blogs' => (int) $row['blog_count'],
            ];
        }

        return $this->panel('admin/users', [
            'activeNav'  => 'users',
            'users'      => $users,
            'search'     => $search,
            'state'      => $state,
            'sort'       => $sort,
            'total'      => $total,
            'page'       => $page,
            'pages'      => max(1, (int) ceil($total / self::PER_PAGE)),
            'adminCount' => self::adminCount(),
        ]);
    }

    public function action(string $id): Response
    {
        if ($denied = $this->guard()) {
            return $denied;
        }
        if ($response = $this->requireCsrf()) {
            return $response;
        }

        $action = $this->request->trimmed('azione');
        if (!in_array($action, self::ACTIONS, true)) {
            $this->flash('error', __('admin.error.unknown_action'));
            return $this->back(Url::to('/admin/utenti'));
        }
        if (in_array($action, self::ADMIN_ONLY, true) && !$this->staff()->isAdmin()) {
            return $this->notFound();
        }

        $user = User::find((int) $id);
        if ($user === null) {
            $this->flash('error', __('admin.users.error.not_found'));
            return $this->redirect(Url::to('/admin/utenti'));
        }

        if ($error = $this->veto($action, $user)) {
            $this->flash('error', $error);
            return $this->back(Url::to('/admin/utenti'));
        }

        $note = $this->note();

        if ($action === 'elimina') {
            return $this->destroy($user, $note);
        }

        return $this->apply($action, $user, $note);
    }

    /**
     * Divieti che valgono prima di qualsiasi azione.
     *
     * Servono a evitare due porte chiuse dall'interno: un amministratore che
     * si toglie i poteri da solo e resta fuori, e l'ultimo amministratore che
     * sparisce lasciando l'installazione senza nessuno che possa entrare.
     */
    private function veto(string $action, User $user): ?string
    {
        $isSelf = $user->id === $this->staff()->id;
        $touchesRole = in_array($action, ['promuovi-moderatore', 'promuovi-admin', 'revoca-ruolo'], true);

        if ($isSelf && $touchesRole) {
            return __('admin.users.error.self_role');
        }
        if ($isSelf && in_array($action, ['elimina', 'sospendi'], true)) {
            return __('admin.users.error.self_account');
        }

        $losesAdmin = $user->isAdmin()
            && in_array($action, ['revoca-ruolo', 'promuovi-moderatore', 'elimina', 'sospendi'], true);

        if ($losesAdmin && self::adminCount() <= 1) {
            return __('admin.users.error.last_admin');
        }

        return null;
    }

    private function apply(string $action, User $user, string $note): Response
    {
        $email = $user->email;

        if ($action === 'verifica-email') {
            $user->markEmailVerified();
        } elseif ($action === 'cambia-limite-blog') {
            $limit = $this->request->int('limite', -1);
            if ($limit < 0 || $limit > 1000) {
                $this->flash('error', __('admin.users.error.invalid_limit'));
                return $this->back(Url::to('/admin/utenti'));
            }
            $user->update(['max_blogs' => $limit]);
            $note = sprintf('max_blogs=%d', $limit) . ($note !== '' ? ' — ' . $note : '');
        } else {
            // User::update scrive il payload così com'è: i booleani vanno
            // passati come interi, altrimenti false finisce nel database come
            // stringa vuota.
            $user->update(match ($action) {
                'sospendi'            => ['is_active' => 0],
                'riattiva'            => ['is_active' => 1],
                'promuovi-moderatore' => ['role' => 'moderator'],
                'promuovi-admin'      => ['role' => 'admin'],
                'revoca-ruolo'        => ['role' => 'user'],
                default               => [],
            });
        }

        $this->record(null, $action, $email . ($note !== '' ? ' — ' . $note : ''));

        $this->flash('success', match ($action) {
            'sospendi'            => __('admin.users.done.sospendi', ['email' => $email]),
            'riattiva'            => __('admin.users.done.riattiva', ['email' => $email]),
            'verifica-email'      => __('admin.users.done.verifica_email', ['email' => $email]),
            'promuovi-moderatore' => __('admin.users.done.promuovi_moderatore', ['email' => $email]),
            'promuovi-admin'      => __('admin.users.done.promuovi_admin', ['email' => $email]),
            'revoca-ruolo'        => __('admin.users.done.revoca_ruolo', ['email' => $email]),
            'cambia-limite-blog'  => __('admin.users.done.cambia_limite', ['email' => $email, 'limit' => $user->max_blogs]),
            default               => __('admin.users.done.generic', ['email' => $email]),
        });

        return $this->back(Url::to('/admin/utenti'));
    }

    /**
     * Eliminazione dell'account: schermata di conferma con l'inventario di
     * quello che sparisce, poi l'email da ridigitare.
     */
    private function destroy(User $user, string $note): Response
    {
        $blogs = Blog::forUser($user->id);

        $footprint = ['posts' => 0, 'pages' => 0, 'files' => 0, 'storage' => 0, 'subscribers' => 0, 'reads' => 0];
        foreach ($blogs as $blog) {
            foreach (self::blogFootprint($blog) as $key => $value) {
                $footprint[$key] += $value;
            }
        }

        if ($this->request->trimmed('conferma') !== $user->email) {
            return $this->panel('admin/user-delete', [
                'activeNav' => 'users',
                'account'   => $user,
                'blogs'     => $blogs,
                'footprint' => $footprint,
                'note'      => $note,
                'mismatch'  => $this->request->trimmed('conferma') !== '',
            ]);
        }

        $email = $user->email;

        $this->record(null, 'elimina-utente', trim(sprintf(
            '%s — blog: %d, articoli: %d, file: %d%s',
            $email,
            count($blogs),
            $footprint['posts'] + $footprint['pages'],
            $footprint['files'],
            $note !== '' ? ' — ' . $note : ''
        )));

        // Le righe dei blog se ne andrebbero comunque per chiave esterna, ma i
        // file caricati no: vanno rimossi finché si conosce il sottodominio.
        foreach ($blogs as $blog) {
            self::purgeBlog($blog);
        }
        $user->delete();

        $this->flash('success', __('admin.users.delete.done', ['email' => $email]));
        return $this->redirect(Url::to('/admin/utenti'));
    }

    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    private function filters(string $search, string $state): array
    {
        $conditions = ['1 = 1'];
        $params = [];

        if ($search !== '') {
            $conditions[] = 'u.email LIKE :search';
            $params['search'] = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%';
        }

        $conditions[] = match ($state) {
            'attivi'         => 'u.is_active = 1',
            'sospesi'        => 'u.is_active = 0',
            'non-verificati' => 'u.email_verified_at IS NULL',
            'staff'          => "u.role IN ('admin','moderator')",
            default          => '1 = 1',
        };

        return [implode(' AND ', $conditions), $params];
    }

    private static function adminCount(): int
    {
        return (int) Database::instance()->fetchColumn(
            "SELECT COUNT(*) FROM {{users}} WHERE role = 'admin' AND is_active = 1"
        );
    }
}
