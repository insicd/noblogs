<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Admin;

use Noblogs\Core\Config;
use Noblogs\Core\Controller;
use Noblogs\Core\Database;
use Noblogs\Core\ErrorHandler;
use Noblogs\Core\Response;
use Noblogs\Core\Session;
use Noblogs\Core\View;
use Noblogs\Models\Blog;
use Noblogs\Models\Media;
use Noblogs\Models\ModerationLog;
use Noblogs\Models\Setting;
use Noblogs\Models\User;

/**
 * Base dei controller dell'area di amministrazione.
 *
 * Raccoglie il controllo degli accessi, la composizione delle pagine e le
 * operazioni condivise da moderatori e amministratori.
 */
abstract class AdminController extends Controller
{
    /** Numero di righe per pagina negli elenchi. */
    protected const PER_PAGE = 30;

    private ?User $staff = null;

    /**
     * Unico punto in cui si decide chi entra nell'area.
     *
     * A chi non ha i permessi si risponde 404 e non 403: un 403 confermerebbe
     * che l'area esiste e dove si trova, che è esattamente l'informazione da
     * cui parte chi vuole forzarla.
     *
     * @param bool $adminOnly Vero per le pagine riservate al ruolo 'admin'
     *                        (impostazioni della piattaforma e ruoli).
     */
    protected function guard(bool $adminOnly = false): ?Response
    {
        $user = $this->user();

        if ($user === null || !$user->isModerator()) {
            return $this->notFound();
        }
        if ($adminOnly && !$user->isAdmin()) {
            return $this->notFound();
        }

        $this->staff = $user;
        return null;
    }

    /** Il moderatore che sta agendo. Valido solo dopo guard(). */
    protected function staff(): User
    {
        if ($this->staff === null) {
            throw new \LogicException('guard() non è stato invocato prima dell\'azione.');
        }
        return $this->staff;
    }

    /**
     * Compone una pagina dell'area.
     *
     * @param array<string,mixed> $data
     */
    protected function panel(string $template, array $data = [], int $status = 200): Response
    {
        $data += [
            'staff'        => $this->staff(),
            'flashes'      => Session::takeFlash(),
            'pendingCount' => self::pendingCount(),
            'activeNav'    => '',
            'notice'       => Setting::get('platform.notice'),
        ];

        return Response::html(View::make($template, $data + ['tenant' => $this->tenant]), $status)
            ->noCache()
            ->noIndex();
    }

    /** Scrive una riga nel registro di moderazione. */
    protected function record(?int $blogId, string $action, string $note = ''): void
    {
        ModerationLog::record($blogId, $this->staff()->id, $action, $note);
    }

    /** Nota facoltativa del moderatore, troncata alla lunghezza della colonna. */
    protected function note(): string
    {
        return mb_substr($this->request->trimmed('nota'), 0, 500);
    }

    protected function pageNumber(): int
    {
        return max(1, min(10000, $this->request->int('pagina', 1)));
    }

    /** Blog che aspettano una decisione: il numero mostrato nel menu. */
    public static function pendingCount(): int
    {
        return (int) Database::instance()->fetchColumn(
            'SELECT COUNT(*) FROM {{blogs}} WHERE to_review = 1 OR reviewed = 0'
        );
    }

    /**
     * Riepilogo di ciò che sparirebbe eliminando un blog: serve alla schermata
     * di conferma e al conteggio nel registro.
     *
     * @return array{posts:int,pages:int,files:int,storage:int,subscribers:int,reads:int}
     */
    protected static function blogFootprint(Blog $blog): array
    {
        $db = Database::instance();

        $row = $db->fetch(
            'SELECT SUM(is_page = 0) AS posts, SUM(is_page = 1) AS pages
             FROM {{posts}} WHERE blog_id = ?',
            [$blog->id]
        );

        $files = $db->fetch(
            'SELECT COUNT(*) AS files, COALESCE(SUM(size), 0) AS bytes FROM {{media}} WHERE blog_id = ?',
            [$blog->id]
        );

        return [
            'posts'       => (int) ($row['posts'] ?? 0),
            'pages'       => (int) ($row['pages'] ?? 0),
            'files'       => (int) ($files['files'] ?? 0),
            'storage'     => (int) ($files['bytes'] ?? 0),
            'subscribers' => (int) $db->fetchColumn('SELECT COUNT(*) FROM {{subscribers}} WHERE blog_id = ?', [$blog->id]),
            'reads'       => (int) $db->fetchColumn('SELECT COUNT(*) FROM {{hits}} WHERE blog_id = ?', [$blog->id]),
        ];
    }

    /**
     * Elimina un blog e tutto ciò che gli appartiene.
     *
     * Le righe collegate se ne vanno con le chiavi esterne; i file caricati
     * no, e vanno rimossi a mano da public/media/<sottodominio>/ prima che la
     * riga sparisca e con essa il nome della cartella.
     */
    protected static function purgeBlog(Blog $blog): void
    {
        try {
            self::removeDirectory(Media::directory($blog));
        } catch (\Throwable $e) {
            // Un file che non si lascia cancellare non deve impedire
            // l'eliminazione: meglio un file orfano che un blog ancora vivo.
            ErrorHandler::log($e);
        }

        \Noblogs\Markdown\Cache::flushBlog($blog->id);
        $blog->delete();
    }

    /**
     * Cancella ricorsivamente una cartella di file caricati.
     *
     * Il percorso viene confrontato con la cartella dei media prima di toccare
     * qualsiasi cosa: un sottodominio malformato non deve poter risalire
     * l'albero.
     */
    protected static function removeDirectory(string $directory): void
    {
        $base = realpath(NOBLOGS_PUBLIC . '/media');
        $target = realpath($directory);

        if ($base === false || $target === false || $target === $base || !str_starts_with($target, $base . '/')) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($target, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($iterator as $item) {
            if (!$item instanceof \SplFileInfo) {
                continue;
            }
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($target);
    }

    /** Valore effettivo di un'impostazione: prima il database, poi i file. */
    protected static function setting(string $key, mixed $fallback = null): mixed
    {
        return Setting::get($key) ?? Config::get($key, $fallback);
    }

    protected static function settingBool(string $key, bool $fallback): bool
    {
        $stored = Setting::get($key);
        return $stored === null ? (bool) Config::get($key, $fallback) : Setting::bool($key, $fallback);
    }
}
