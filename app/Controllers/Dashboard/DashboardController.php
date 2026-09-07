<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Dashboard;

use Noblogs\Core\Auth;
use Noblogs\Core\Config;
use Noblogs\Core\Controller;
use Noblogs\Core\I18n;
use Noblogs\Core\Request;
use Noblogs\Core\Response;
use Noblogs\Core\Session;
use Noblogs\Core\Tenant;
use Noblogs\Core\Url;
use Noblogs\Core\View;
use Noblogs\Models\Blog;
use Noblogs\Models\Media;
use Noblogs\Models\User;

/**
 * Base dei controller del pannello dell'utente.
 *
 * Concentra qui le tre cose che ogni azione della dashboard deve fare prima di
 * toccare i dati: verificare l'accesso, verificare che il blog nell'URL sia
 * davvero dell'utente, e comporre la pagina con il layout del pannello.
 */
abstract class DashboardController extends Controller
{
    protected ?User $account;

    /**
     * Risposta d'errore prodotta dall'ultima resolveBlog() fallita.
     * Serve perché resolveBlog() restituisce il blog o null, e la distinzione
     * tra «non esiste» e «non è tuo» va conservata da qualche parte.
     */
    private ?Response $resolveFailure = null;

    public function __construct(Request $request, Tenant $tenant)
    {
        parent::__construct($request, $tenant);

        $this->account = Auth::user();

        // Il pannello parla la lingua scelta dall'utente, non quella della
        // piattaforma né quella dei blog che sta amministrando.
        if ($this->account !== null) {
            I18n::load($this->account->locale);
        }
    }

    // -----------------------------------------------------------------------
    // Accesso
    // -----------------------------------------------------------------------

    /**
     * Verifica l'accesso. Restituisce la risposta da rimandare al router se la
     * richiesta non può proseguire, altrimenti null.
     */
    protected function requireAuth(bool $requireVerifiedEmail = false): ?Response
    {
        if ($this->account === null) {
            $next = $this->request->path;
            return $this->redirect(Url::to('/accedi') . '?next=' . rawurlencode($next));
        }

        if ($requireVerifiedEmail && !$this->account->hasVerifiedEmail()) {
            $this->flash('warning', __('dashboard.email_not_verified'));
            return $this->redirect(Url::to('/dashboard'));
        }

        return null;
    }

    /** L'utente collegato, garantito non nullo dopo requireAuth(). */
    protected function account(): User
    {
        if ($this->account === null) {
            throw new \LogicException('Azione della dashboard eseguita senza utente collegato.');
        }
        return $this->account;
    }

    /**
     * Carica il blog indicato nell'URL controllando che appartenga
     * all'utente. Chi amministra la piattaforma passa comunque (Blog::isOwnedBy
     * lo consente) perché deve poter intervenire sui contenuti segnalati.
     */
    protected function resolveBlog(string $subdomain): ?Blog
    {
        $this->resolveFailure = null;

        $blog = Blog::findBySubdomain($subdomain);
        if ($blog === null) {
            $this->resolveFailure = $this->notFound(__('dashboard.blog_missing'));
            return null;
        }

        if (!$blog->isOwnedBy($this->account)) {
            $this->resolveFailure = $this->forbidden(__('dashboard.blog_not_yours'));
            return null;
        }

        return $blog;
    }

    /**
     * Accesso più il caricamento del blog in un solo passaggio: restituisce il
     * blog oppure la risposta (redirect, 403, 404) da rimandare al router.
     */
    protected function openBlog(string $subdomain, bool $requireVerifiedEmail = true): Blog|Response
    {
        $guard = $this->requireAuth($requireVerifiedEmail);
        if ($guard !== null) {
            return $guard;
        }

        $blog = $this->resolveBlog($subdomain);
        if ($blog === null) {
            return $this->resolveFailure ?? $this->notFound();
        }

        return $blog;
    }

    // -----------------------------------------------------------------------
    // Presentazione
    // -----------------------------------------------------------------------

    /**
     * Compone una pagina del pannello.
     *
     * @param array<string,mixed> $data
     */
    protected function panel(string $template, array $data = [], int $status = 200): Response
    {
        $data += [
            'user'      => $this->account,
            'blog'      => null,
            'section'   => '',
            'pageTitle' => (string) Config::get('site.name', 'Noblogs'),
            // I valori di un form fallito sopravvivono a un solo redirect: si
            // consumano qui, alla prima pagina che li può usare.
            'old'       => Session::takeOldInput(),
        ];

        return Response::html(View::make($template, $data + ['tenant' => $this->tenant]), $status)
            ->noCache()
            ->noIndex();
    }

    /**
     * Le pagine d'errore della dashboard restano dentro il pannello: chi ha
     * sbagliato un indirizzo ha ancora la navigazione per tornare al lavoro.
     */
    protected function notFound(string $message = ''): Response
    {
        return $this->errorPanel(
            __('dashboard.error.not_found_title'),
            $message !== '' ? $message : __('dashboard.error.not_found_body'),
            404
        );
    }

    protected function forbidden(string $message = ''): Response
    {
        return $this->errorPanel(
            __('dashboard.error.forbidden_title'),
            $message !== '' ? $message : __('error.forbidden'),
            403
        );
    }

    private function errorPanel(string $heading, string $message, int $status): Response
    {
        // Il layout del pannello ha bisogno dell'utente: senza sessione valida
        // si risponde in testo semplice invece di far esplodere il template.
        if ($this->account === null) {
            return Response::text($heading . "\n\n" . $message, $status)->noCache();
        }

        return $this->panel('dashboard/error', [
            'pageTitle' => $heading,
            'heading'   => $heading,
            'message'   => $message,
        ], $status);
    }

    /** Redirect dopo un POST riuscito (schema PRG) con messaggio di conferma. */
    protected function succeed(string $location, string $message): Response
    {
        $this->flash('success', $message);
        return $this->redirect($location);
    }

    // -----------------------------------------------------------------------
    // Utilità condivise
    // -----------------------------------------------------------------------

    protected function limit(string $key, int $default): int
    {
        return (int) Config::get('limits.' . $key, $default);
    }

    /** Radice degli URL del pannello di un blog. */
    protected function blogUrl(Blog $blog, string $path = ''): string
    {
        return Url::to('/dashboard/' . $blog->subdomain . $path);
    }

    /**
     * Elenco dei file del blog nella forma attesa dall'editor: nome, indirizzo
     * pubblico e markdown già pronto da inserire.
     *
     * @return list<array{name:string,url:string,markdown:string,image:bool}>
     */
    protected function mediaCatalogue(Blog $blog): array
    {
        $items = [];
        foreach (Media::forBlog($blog, 200) as $media) {
            $url = Url::media($blog, $media->path);
            $items[] = [
                'name'     => $media->filename,
                'url'      => $url,
                'markdown' => $media->isImage()
                    ? '![' . $media->filename . '](' . $url . ')'
                    : '[' . $media->filename . '](' . $url . ')',
                'image'    => $media->isImage(),
            ];
        }
        return $items;
    }

    /**
     * Riporta $_FILES a una lista di file singoli.
     *
     * Con name="campo[]" PHP produce array paralleli (tutti i nomi, tutti gli
     * errori, ...) invece di una voce per file, mentre chi consuma un file
     * caricato vuole la forma singola.
     *
     * @return list<array{name:string,type:string,tmp_name:string,error:int,size:int}>
     */
    protected function uploadedFiles(string $key): array
    {
        $entry = $this->request->files[$key] ?? null;
        if (!is_array($entry) || !isset($entry['name'])) {
            return [];
        }

        if (!is_array($entry['name'])) {
            $error = (int) ($entry['error'] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                return [];
            }
            return [[
                'name'     => (string) $entry['name'],
                'type'     => (string) ($entry['type'] ?? ''),
                'tmp_name' => (string) ($entry['tmp_name'] ?? ''),
                'error'    => $error,
                'size'     => (int) ($entry['size'] ?? 0),
            ]];
        }

        $files = [];
        foreach (array_keys($entry['name']) as $index) {
            $error = (int) ($entry['error'][$index] ?? UPLOAD_ERR_NO_FILE);
            if ($error === UPLOAD_ERR_NO_FILE) {
                continue;
            }
            $files[] = [
                'name'     => (string) $entry['name'][$index],
                'type'     => (string) ($entry['type'][$index] ?? ''),
                'tmp_name' => (string) ($entry['tmp_name'][$index] ?? ''),
                'error'    => $error,
                'size'     => (int) ($entry['size'][$index] ?? 0),
            ];
        }

        return $files;
    }

    /**
     * Elimina un blog con tutto ciò che gli appartiene.
     *
     * Le righe di post, file, iscritti e statistiche se ne vanno da sole per
     * effetto delle chiavi esterne; i file su disco no, e vanno rimossi qui
     * altrimenti restano occupati sia lo spazio sia il nome della cartella.
     */
    protected function deleteBlogCompletely(Blog $blog): void
    {
        foreach (Media::forBlog($blog, 100000) as $media) {
            $path = $media->absolutePath($blog);
            if (is_file($path)) {
                unlink($path);
            }
        }

        $directory = Media::directory($blog);
        if (is_dir($directory)) {
            foreach (glob($directory . '/*') ?: [] as $leftover) {
                if (is_file($leftover)) {
                    unlink($leftover);
                }
            }
            rmdir($directory);
        }

        \Noblogs\Markdown\Cache::flushBlog($blog->id);
        $blog->delete();
    }

    /**
     * Etichette della barra degli strumenti, passate all'editor come JSON:
     * le stringhe restano nei cataloghi di lingua e non nel JavaScript.
     *
     * @return array<string,string>
     */
    protected function editorLabels(): array
    {
        $keys = [
            'toolbar', 'bold', 'italic', 'strike', 'heading', 'link', 'image',
            'quote', 'ul', 'ol', 'task', 'code', 'codeblock', 'table', 'hr',
            'footnote', 'insert',
            'preview_show', 'preview_hide', 'preview_title', 'preview_failed',
            'media_title', 'media_upload', 'media_empty',
            'words', 'reading_time', 'uploading', 'upload_failed',
            'draft_found', 'draft_restore', 'draft_discard', 'unsaved_warning',
            'ph_text', 'ph_url', 'ph_code', 'ph_note', 'table_column',
            'directive_posts', 'directive_tags', 'directive_toc',
            'directive_archive', 'directive_subscribe', 'directive_search',
            'directive_postnav',
        ];

        $labels = [];
        foreach ($keys as $key) {
            $labels[$key] = __('editor.' . $key);
        }
        return $labels;
    }
}
