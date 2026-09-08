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
    public function info(string $uid): Response
    {
        $post = $this->resolvePost($uid);
        if ($post === null) {
            return Response::json(['error' => 'not_found'], 404)->noCache()->noIndex();
        }

        $hashId = Upvote::identify($this->request->ip());

        return Response::json([
            'count'   => $post->effectiveUpvotes(),
            'voted'   => Upvote::exists($post->id, $hashId),
            // Token a breve scadenza legato al post e all'identità: rende
            // inutile riusare una richiesta catturata altrove.
            'token'   => Csrf::sign('upvote:' . $post->uid . ':' . $hashId, 43200),
            'enabled' => $this->blog->upvotes_active,
        ])->noCache()->noIndex()
            ->withHeader('CDN-Cache-Control', 'no-store');
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

        $ip = $this->request->ip();
        $hashId = Upvote::identify($ip);

        if (RateLimiter::tooManyAttempts('upvote:' . RateLimiter::hashIp($ip), 30, 300)) {
            return Response::json(['error' => 'rate_limited'], 429)->noCache();
        }

        $signature = $this->request->input('token', '') ?? '';
        if (!Csrf::verifySigned('upvote:' . $post->uid . ':' . $hashId, $signature)) {
            return Response::json(['error' => 'invalid_token'], 400)->noCache();
        }

        if (Upvote::exists($post->id, $hashId)) {
            Upvote::remove($post, $hashId);
            return Response::json([
                'count'   => $post->effectiveUpvotes(),
                'voted'   => false,
                'enabled' => true,
            ])->noCache();
        }

        // I voti sospetti vengono registrati ma non conteggiati: chi li invia
        // vede il pulsante cambiare stato e non ha motivo di riprovare.
        Upvote::cast($post, $hashId, $this->suspicionSignals());

        return Response::json([
            'count'   => $post->effectiveUpvotes(),
            'voted'   => true,
            'enabled' => true,
        ])->noCache();
    }

    /** @return list<string> */
    private function suspicionSignals(): array
    {
        $signals = [];

        // Il campo esca è invisibile: solo un programma lo compila.
        if (trim($this->request->input('website', '') ?? '') !== '') {
            $signals[] = 'honeypot';
        }
        // Lo script imposta questo campo dopo il primo movimento del puntatore.
        if ($this->request->input('interacted') !== '1') {
            $signals[] = 'no_interaction';
        }
        if ($this->request->userAgent() === '') {
            $signals[] = 'no_user_agent';
        }
        if ($this->request->header('Sec-Fetch-Site') === null && $this->request->referrer() === '') {
            $signals[] = 'no_origin';
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
