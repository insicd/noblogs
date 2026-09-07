<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Dashboard;

use Noblogs\Core\Response;
use Noblogs\Core\Session;
use Noblogs\Markdown\Frontmatter;
use Noblogs\Models\Blog;
use Noblogs\Models\Post;
use Noblogs\Support\Dates;
use Noblogs\Support\Str;

/**
 * Esportazione e importazione dei contenuti di un blog.
 *
 * Il formato è un file markdown per articolo, con l'intestazione di
 * Frontmatter: si legge con qualunque editor di testo e si può reimportare
 * qui o altrove. Un archivio che si apre solo con Noblogs non sarebbe una
 * copia dei propri scritti, sarebbe un altro modo di tenerli in ostaggio.
 */
final class ImportExportController extends DashboardController
{
    /** Articoli accettati in un solo caricamento. */
    private const MAX_IMPORT_FILES = 200;

    /** Testo complessivo accettato in un caricamento: l'anteprima sta in sessione. */
    private const MAX_IMPORT_BYTES = 4194304;

    private const IMPORT_EXTENSIONS = ['md', 'markdown', 'txt'];

    public function export(string $blog): Response
    {
        $found = $this->openBlog($blog);
        if ($found instanceof Response) {
            return $found;
        }

        $posts = array_merge(Post::forDashboard($found, false), Post::forDashboard($found, true));
        $stamp = gmdate('Y-m-d');

        if ($this->request->query('formato') === 'csv') {
            return Response::download(
                $this->composeCsv($posts),
                $found->subdomain . '-' . $stamp . '.csv',
                'text/csv; charset=UTF-8'
            )->noCache();
        }

        $documents = [];
        foreach ($posts as $post) {
            $documents[$this->fileNameFor($post)] = $this->composeDocument($post);
        }

        $archive = class_exists(\ZipArchive::class) ? $this->composeZip($documents) : null;
        if ($archive !== null) {
            return Response::download(
                $archive,
                $found->subdomain . '-' . $stamp . '.zip',
                'application/zip'
            )->noCache();
        }

        // Senza ZipArchive si esce con un unico markdown: meglio un file solo
        // che nessuna via d'uscita.
        return Response::download(
            $this->composeSingleFile($found, $documents),
            $found->subdomain . '-' . $stamp . '.md',
            'text/markdown; charset=UTF-8'
        )->noCache();
    }

    public function import(string $blog): Response
    {
        $found = $this->openBlog($blog);
        if ($found instanceof Response) {
            return $found;
        }

        $return = $this->blogUrl($found, '/importa');

        if ($this->request->isPost()) {
            $csrf = $this->requireCsrf();
            if ($csrf !== null) {
                return $csrf;
            }

            return $this->request->trimmed('azione') === 'conferma'
                ? $this->apply($found, $return)
                : $this->prepare($found, $return);
        }

        return $this->panel('dashboard/import', [
            'pageTitle' => __('import.title'),
            'section'   => 'import',
            'blog'      => $found,
            'items'     => [],
            'zip'       => class_exists(\ZipArchive::class),
            'maxFiles'  => self::MAX_IMPORT_FILES,
        ]);
    }

    // -----------------------------------------------------------------------
    // Esportazione
    // -----------------------------------------------------------------------

    private function composeDocument(Post $post): string
    {
        $published = $post->publishedAt();

        return Frontmatter::compose([
            'titolo'     => $post->title,
            'link'       => $post->slug,
            'alias'      => $post->alias,
            'data'       => $published?->format('Y-m-d H:i:s'),
            'pubblicato' => $post->is_published,
            'pagina'     => $post->is_page,
            'in_vetrina' => $post->make_discoverable,
            'tag'        => $post->tagList(),
            'descrizione' => $post->meta_description,
            'immagine'   => $post->meta_image,
            'canonical'  => $post->canonical_url,
            'lingua'     => $post->lang,
            'classe'     => $post->class_name,
        ], $post->content ?? '');
    }

    private function fileNameFor(Post $post): string
    {
        $date = $post->publishedAt()?->format('Y-m-d') ?? '0000-00-00';
        $slug = $post->slug !== '' ? $post->slug : 'post-' . $post->id;
        // Le pagine in una cartella a parte: reimportandole si riconoscono
        // anche senza leggere l'intestazione.
        $prefix = $post->is_page ? 'pagine/' : 'articoli/';

        return $prefix . ($post->is_page ? '' : $date . '-') . str_replace('/', '-', $slug) . '.md';
    }

    /** @param array<string,string> $documents */
    private function composeZip(array $documents): ?string
    {
        $directory = NOBLOGS_STORAGE . '/tmp';
        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            return null;
        }

        $path = $directory . '/export-' . bin2hex(random_bytes(8)) . '.zip';
        $zip = new \ZipArchive();
        if ($zip->open($path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            return null;
        }

        foreach ($documents as $name => $contents) {
            $zip->addFromString($name, $contents);
        }
        $zip->close();

        $payload = file_get_contents($path);
        unlink($path);

        return $payload === false ? null : $payload;
    }

    /** @param array<string,string> $documents */
    private function composeSingleFile(Blog $blog, array $documents): string
    {
        $parts = ['# ' . $blog->title, ''];
        foreach ($documents as $name => $contents) {
            // Un separatore in commento HTML: tre trattini sarebbero letti
            // come inizio di una nuova intestazione.
            $parts[] = '<!-- ' . $name . ' -->';
            $parts[] = $contents;
            $parts[] = '';
        }

        return implode("\n", $parts);
    }

    /** @param list<Post> $posts */
    private function composeCsv(array $posts): string
    {
        $rows = [[
            'id', 'tipo', 'titolo', 'slug', 'alias', 'tag', 'data', 'pubblicato',
            'in_vetrina', 'lingua', 'apprezzamenti', 'caratteri', 'descrizione', 'contenuto',
        ]];

        foreach ($posts as $post) {
            $rows[] = [
                $post->id,
                $post->is_page ? 'pagina' : 'articolo',
                $post->title,
                $post->slug,
                $post->alias ?? '',
                implode(', ', $post->tagList()),
                $post->published_at,
                $post->is_published ? 'sì' : 'no',
                $post->make_discoverable ? 'sì' : 'no',
                $post->lang ?? '',
                $post->effectiveUpvotes(),
                $post->content_length,
                $post->meta_description ?? '',
                $post->content ?? '',
            ];
        }

        $lines = [];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(
                // Sempre fra virgolette: il contenuto contiene virgole e
                // interruzioni di riga, e le virgolette si raddoppiano.
                static fn (string|int|null $value): string
                    => '"' . str_replace('"', '""', (string) $value) . '"',
                $row
            ));
        }

        return implode("\r\n", $lines) . "\r\n";
    }

    // -----------------------------------------------------------------------
    // Importazione
    // -----------------------------------------------------------------------

    /** Primo passaggio: si legge, si mostra, non si scrive niente. */
    private function prepare(Blog $blog, string $return): Response
    {
        $documents = [];
        $errors = [];
        $bytes = 0;

        foreach ($this->uploadedFiles('file') as $upload) {
            if ($upload['error'] !== UPLOAD_ERR_OK || !is_readable($upload['tmp_name'])) {
                $errors[] = __('import.error.upload', ['name' => $upload['name']]);
                continue;
            }

            $extension = mb_strtolower(pathinfo($upload['name'], PATHINFO_EXTENSION));

            if ($extension === 'zip') {
                foreach ($this->readZip($upload['tmp_name'], $errors) as $name => $contents) {
                    $documents[$name] = $contents;
                    $bytes += strlen($contents);
                }
                continue;
            }

            if (!in_array($extension, self::IMPORT_EXTENSIONS, true)) {
                $errors[] = __('import.error.extension', ['name' => $upload['name']]);
                continue;
            }

            $contents = file_get_contents($upload['tmp_name']);
            if ($contents === false) {
                $errors[] = __('import.error.upload', ['name' => $upload['name']]);
                continue;
            }
            $documents[$upload['name']] = $contents;
            $bytes += strlen($contents);
        }

        if ($documents === []) {
            foreach ($errors as $error) {
                $this->flash('error', $error);
            }
            $this->flash('error', __('import.error.nothing'));
            return $this->redirect($return);
        }

        if ($bytes > self::MAX_IMPORT_BYTES) {
            $this->flash('error', __('import.error.too_big', [
                'size' => \Noblogs\Models\Media::humanBytes(self::MAX_IMPORT_BYTES),
            ]));
            return $this->redirect($return);
        }

        $items = [];
        foreach (array_slice($documents, 0, self::MAX_IMPORT_FILES, true) as $name => $contents) {
            $items[] = $this->describe($blog, (string) $name, $contents);
        }

        Session::put($this->sessionKey($blog), $items);

        return $this->panel('dashboard/import', [
            'pageTitle' => __('import.title'),
            'section'   => 'import',
            'blog'      => $blog,
            'items'     => $items,
            'zip'       => class_exists(\ZipArchive::class),
            'maxFiles'  => self::MAX_IMPORT_FILES,
            'errors'    => $errors,
            'skipped'   => max(0, count($documents) - count($items)),
        ]);
    }

    /** Secondo passaggio: si scrive quello che l'anteprima aveva mostrato. */
    private function apply(Blog $blog, string $return): Response
    {
        $items = Session::get($this->sessionKey($blog), []);
        if (!is_array($items) || $items === []) {
            $this->flash('error', __('import.error.expired'));
            return $this->redirect($return);
        }
        Session::forget($this->sessionKey($blog));

        $strategy = $this->request->trimmed('conflitti');
        if (!in_array($strategy, ['salta', 'rinomina', 'sovrascrivi'], true)) {
            $strategy = 'rinomina';
        }

        $created = 0;
        $updated = 0;
        $skipped = 0;

        foreach ($items as $item) {
            if (!is_array($item)) {
                continue;
            }
            if ($blog->isAtPostLimit()) {
                $skipped++;
                continue;
            }

            $existing = Post::findBySlug($blog, (string) $item['slug']);

            if ($existing !== null && $strategy === 'salta') {
                $skipped++;
                continue;
            }

            if ($existing !== null && $strategy === 'sovrascrivi') {
                $post = $existing;
                $slug = $existing->slug;
                $updated++;
            } else {
                $post = Post::make($blog);
                $slug = Post::uniqueSlug($blog, (string) $item['slug']);
                $created++;
            }

            $post->fill([
                'title'             => (string) $item['title'],
                'slug'              => $slug,
                'content'           => (string) $item['content'],
                'is_page'           => (bool) $item['is_page'],
                'is_published'      => (bool) $item['is_published'],
                'make_discoverable' => (bool) $item['make_discoverable'],
                'meta_description'  => $item['meta_description'],
                'meta_image'        => $item['meta_image'],
                'canonical_url'     => $item['canonical_url'],
                'lang'              => $item['lang'],
                'published_at'      => (string) $item['published_at'],
            ]);
            $post->setTags(is_array($item['tags']) ? $item['tags'] : []);
            $post->save();
        }

        $blog->refreshTags();
        $blog->refreshCounters();

        return $this->succeed($this->blogUrl($blog, '/articoli'), __('import.done', [
            'created' => $created,
            'updated' => $updated,
            'skipped' => $skipped,
        ]));
    }

    /**
     * Descrive un documento senza salvarlo: è la riga che l'utente vede
     * nell'anteprima e che finisce in sessione fino alla conferma.
     *
     * @return array<string,mixed>
     */
    private function describe(Blog $blog, string $name, string $contents): array
    {
        [$meta, $body, $warnings] = Frontmatter::parse($contents);

        $title = is_string($meta['title'] ?? null) && trim($meta['title']) !== ''
            ? trim($meta['title'])
            : $this->titleFromBody($body, $name);

        $slug = is_string($meta['slug'] ?? null) && trim($meta['slug']) !== ''
            ? Str::slug($meta['slug'], '-', true)
            : Str::slug($this->slugFromName($name));
        if ($slug === '') {
            $slug = Str::slug($title) ?: 'post';
        }

        $date = is_string($meta['published_at'] ?? null)
            ? Dates::fromLocal($meta['published_at'], $this->account()->timezone)
            : null;
        $date ??= $this->dateFromName($name) ?? Dates::now();

        $tags = $meta['tags'] ?? [];

        return [
            'file'              => $name,
            'title'             => mb_substr($title, 0, 200),
            'slug'              => mb_substr($slug, 0, 200),
            'is_page'           => (bool) ($meta['is_page'] ?? str_starts_with($name, 'pagine/')),
            'is_published'      => (bool) ($meta['is_published'] ?? true),
            'make_discoverable' => (bool) ($meta['make_discoverable'] ?? true),
            'meta_description'  => $this->stringOrNull($meta['meta_description'] ?? null, 300),
            'meta_image'        => $this->stringOrNull($meta['meta_image'] ?? null, 300),
            'canonical_url'     => $this->stringOrNull($meta['canonical_url'] ?? null, 300),
            'lang'              => $this->stringOrNull($meta['lang'] ?? null, 10),
            'published_at'      => $date->format('Y-m-d H:i:s'),
            'tags'              => is_array($tags) ? $tags : Str::tags((string) $tags),
            'content'           => $body,
            'conflict'          => Post::slugTaken($blog, $slug),
            'warnings'          => $warnings,
            'chars'             => mb_strlen($body),
        ];
    }

    /**
     * @param list<string> $errors
     * @return array<string,string>
     */
    private function readZip(string $path, array &$errors): array
    {
        if (!class_exists(\ZipArchive::class)) {
            $errors[] = __('import.error.no_zip');
            return [];
        }

        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            $errors[] = __('import.error.zip_unreadable');
            return [];
        }

        $documents = [];
        $total = 0;

        for ($index = 0; $index < $zip->numFiles; $index++) {
            $stat = $zip->statIndex($index);
            if ($stat === false) {
                continue;
            }

            $name = (string) $stat['name'];
            if (str_ends_with($name, '/') || str_contains($name, '__MACOSX')) {
                continue;
            }
            if (!in_array(mb_strtolower(pathinfo($name, PATHINFO_EXTENSION)), self::IMPORT_EXTENSIONS, true)) {
                continue;
            }

            // Somma dichiarata dall'archivio: si controlla prima di estrarre,
            // così uno zip gonfiato non finisce in memoria.
            $total += (int) $stat['size'];
            if ($total > self::MAX_IMPORT_BYTES) {
                $errors[] = __('import.error.too_big', [
                    'size' => \Noblogs\Models\Media::humanBytes(self::MAX_IMPORT_BYTES),
                ]);
                break;
            }

            $contents = $zip->getFromIndex($index);
            if ($contents !== false) {
                $documents[$name] = $contents;
            }
        }

        $zip->close();

        return $documents;
    }

    private function titleFromBody(string $body, string $name): string
    {
        foreach (explode("\n", $body) as $line) {
            if (preg_match('/^#{1,3}\s+(.+)$/', trim($line), $matches) === 1) {
                return trim($matches[1]);
            }
        }
        return $this->slugFromName($name);
    }

    private function slugFromName(string $name): string
    {
        $base = pathinfo($name, PATHINFO_FILENAME);
        // I file esportati hanno il prefisso della data: non appartiene allo slug.
        return (string) preg_replace('/^\d{4}-\d{2}-\d{2}-/', '', $base);
    }

    private function dateFromName(string $name): ?\DateTimeImmutable
    {
        if (preg_match('/(\d{4}-\d{2}-\d{2})/', basename($name), $matches) === 1) {
            return Dates::parse($matches[1] . ' 12:00:00');
        }
        return null;
    }

    private function stringOrNull(mixed $value, int $max): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }
        return mb_substr(trim($value), 0, $max);
    }

    private function sessionKey(Blog $blog): string
    {
        return '_import:' . $blog->id;
    }
}
