<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Site;

use Noblogs\Core\Csrf;
use Noblogs\Core\RateLimiter;
use Noblogs\Core\Response;
use Noblogs\Models\Post;
use Noblogs\Models\Upvote;

/**
 * Apprezzamenti ai post.
 *
 * Il contatore non viene messo nell'HTML della pagina ma caricato a parte: è
 * quello che permette di tenere le pagine in cache per ore pur mostrando un
 * numero aggiornato.
 */
final class UpvoteController extends SiteController
{
    /** Stato del voto per il visitatore corrente. */
    public function info(?string $uid = null): Response
    {
        $uid = trim((string) ($uid ?? $this->request->query('uid') ?? ''));
        $post = $this->resolvePost($uid);
        if ($post === null) {
            return Response::json(['error' => 'not_found'], 404)->noCache()->noIndex();
        }

        try {
            $hashId = Upvote::identify($this->request->ip());

            return Response::json([
                'count'   => $post->effectiveUpvotes(),
                'voted'   => Upvote::isValid($post->id, $hashId),
                'token'   => Csrf::sign('upvote:' . $post->uid, 43200),
                'enabled' => $this->blog->upvotes_active,
            ])->noCache()->noIndex()
                ->withHeader('CDN-Cache-Control', 'no-store');
        } catch (\Throwable $e) {
            \Noblogs\Core\ErrorHandler::log($e);

            return Response::json(['error' => 'unavailable'], 500)->noCache();
        }
    }

    public function toggle(): Response
    {
        if (!$this->blog->upvotes_active) {
            return Response::json(['error' => 'disabled', 'enabled' => false], 403)->noCache();
        }

        $post = $this->resolvePost(trim($this->request->input('uid', '') ?? ''));
        if ($post === null) {
            return Response::json(['error' => 'not_found'], 404)->noCache();
        }

        $signature = $this->request->input('token', '') ?? '';
        if (!Csrf::verifySigned('upvote:' . $post->uid, $signature)) {
            return Response::json(['error' => 'invalid_token'], 400)->noCache();
        }

        try {
            $ip = $this->request->ip();
            $hashId = Upvote::identify($ip);
            $signals = $this->suspicionSignals();

            if (RateLimiter::tooManyAttempts('upvote:' . RateLimiter::hashIp($ip), 30, 300)) {
                return Response::json(['error' => 'rate_limited'], 429)->noCache();
            }

            $wantVote = $this->request->boolean('voted');

            if ($wantVote) {
                if (Upvote::isMarked($post->id, $hashId)) {
                    Upvote::confirm($post, $hashId);
                } elseif (!Upvote::exists($post->id, $hashId)) {
                    Upvote::cast($post, $hashId, $signals);
                }
            } elseif (Upvote::exists($post->id, $hashId)) {
                Upvote::remove($post, $hashId);
            }

            return Response::json([
                'count'   => $post->effectiveUpvotes(),
                'voted'   => Upvote::isValid($post->id, $hashId),
                'enabled' => true,
                'token'   => Csrf::sign('upvote:' . $post->uid, 43200),
            ])->noCache();
        } catch (\Throwable $e) {
            \Noblogs\Core\ErrorHandler::log($e);

            return Response::json(['error' => 'unavailable'], 500)->noCache();
        }
    }

    /** @return list<string> */
    private function suspicionSignals(): array
    {
        $signals = [];

        // Il campo esca è invisibile: solo un programma lo compila.
        if (trim($this->request->input('website', '') ?? '') !== '') {
            $signals[] = 'honeypot';
        }

        return $signals;
    }

    private function resolvePost(string $uid): ?Post
    {
        if ($uid === '' || mb_strlen($uid) > 32) {
            return null;
        }
        $post = Post::findByUid($uid);
        if ($post === null || $post->blog_id !== $this->blog->id || !$post->isVisible()) {
            return null;
        }
        return $post;
    }
}
