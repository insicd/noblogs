<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Dashboard;

use Noblogs\Core\Response;
use Noblogs\Core\Url;
use Noblogs\Models\Blog;
use Noblogs\Models\Media;

/**
 * File caricati da un blog: elenco, caricamento e cancellazione.
 *
 * Il caricamento risponde anche alle richieste dell'editor, che carica le
 * immagini trascinate o incollate e si aspetta un JSON con l'indirizzo e il
 * markdown già pronto.
 */
final class MediaController extends DashboardController
{
    /** File accettati in una sola richiesta. */
    private const MAX_PER_REQUEST = 20;

    public function index(string $blog): Response
    {
        $found = $this->openBlog($blog);
        if ($found instanceof Response) {
            return $found;
        }

        if ($this->request->isPost()) {
            return $this->upload($found);
        }

        $quota = $this->limit('storage_per_blog', 524288000);

        return $this->panel('dashboard/media', [
            'pageTitle'  => __('media.title'),
            'section'    => 'media',
            'blog'       => $found,
            'files'      => Media::forBlog($found),
            'used'       => $found->storage_used,
            'quota'      => $quota,
            'usedHuman'  => Media::humanBytes($found->storage_used),
            'quotaHuman' => Media::humanBytes($quota),
            'percent'    => $quota > 0 ? min(100, (int) round($found->storage_used / $quota * 100)) : 0,
            'count'      => Media::countForBlog($found),
            'maxFiles'   => $this->limit('files_per_blog', 2000),
            'maxBytes'   => Media::humanBytes($this->limit('upload_max_bytes', 10485760)),
        ]);
    }

    public function destroy(string $blog, string $id): Response
    {
        $found = $this->openBlog($blog);
        if ($found instanceof Response) {
            return $found;
        }

        $csrf = $this->requireCsrf();
        if ($csrf !== null) {
            return $csrf;
        }

        $media = Media::findForBlog($found, (int) $id);
        if ($media === null) {
            return $this->notFound(__('media.not_found'));
        }

        $name = $media->filename;
        $media->deleteWithFile($found);

        return $this->succeed($this->blogUrl($found, '/file'), __('media.deleted', ['name' => $name]));
    }

    // -----------------------------------------------------------------------
    // Interno
    // -----------------------------------------------------------------------

    private function upload(Blog $blog): Response
    {
        $wantsJson = $this->request->isAjax()
            || str_contains(mb_strtolower($this->request->header('Accept') ?? ''), 'application/json');

        $csrf = $this->requireCsrf();
        if ($csrf !== null) {
            return $wantsJson
                ? Response::json(['ok' => false, 'error' => __('error.csrf')], 403)->noCache()
                : $csrf;
        }

        $uploads = array_slice($this->uploadedFiles('file'), 0, self::MAX_PER_REQUEST);
        $stored = [];
        $errors = [];

        foreach ($uploads as $upload) {
            [$media, $error] = Media::store($blog, $upload);
            if ($media === null) {
                if ($error !== null) {
                    $errors[] = $error;
                }
                continue;
            }
            $url = Url::media($blog, $media->path);
            $stored[] = [
                'name'     => $media->filename,
                'url'      => $url,
                'markdown' => $media->isImage()
                    ? '![' . $media->filename . '](' . $url . ')'
                    : '[' . $media->filename . '](' . $url . ')',
            ];
        }

        if ($wantsJson) {
            if ($stored === []) {
                return Response::json([
                    'ok'    => false,
                    'error' => $errors[0] ?? __('media.error.no_file'),
                ], 422)->noCache();
            }
            // Il primo file è replicato in cima alla risposta: l'editor che ne
            // carica uno solo non deve frugare nell'elenco.
            return Response::json([
                'ok'       => true,
                'url'      => $stored[0]['url'],
                'markdown' => $stored[0]['markdown'],
                'files'    => $stored,
                'errors'   => $errors,
            ])->noCache();
        }

        foreach ($errors as $error) {
            $this->flash('error', $error);
        }
        if ($stored !== []) {
            $this->flash('success', __('media.uploaded', ['count' => count($stored)]));
        }

        return $this->redirect($this->blogUrl($blog, '/file'));
    }
}
