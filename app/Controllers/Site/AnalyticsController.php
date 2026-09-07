<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Site;

use Noblogs\Core\RateLimiter;
use Noblogs\Core\Response;
use Noblogs\Models\Hit;
use Noblogs\Models\Post;

/**
 * Registrazione di una lettura.
 *
 * La chiamata arriva da uno script che parte solo dopo un'interazione reale
 * del visitatore: i programmi che scaricano pagine non muovono il puntatore,
 * quindi non compaiono nei conteggi senza bisogno di riconoscerli uno a uno.
 */
final class AnalyticsController extends SiteController
{
    public function record(): Response
    {
        // La risposta è sempre la stessa, qualunque cosa accada: chi manda la
        // richiesta non deve poter dedurre nulla dal risultato.
        $ok = Response::noContent()->noCache()->noIndex();

        if (!$this->blog->analytics_active) {
            return $ok;
        }

        $userAgent = $this->request->userAgent();
        if ($this->looksAutomated($userAgent)) {
            return $ok;
        }

        $ip = $this->request->ip();

        // Tetto generoso: serve solo a fermare chi invia migliaia di richieste
        // per gonfiare i numeri, non un lettore che apre venti articoli.
        if (RateLimiter::tooManyAttempts('hit:' . RateLimiter::hashIp($ip), 120, 60)) {
            return $ok;
        }

        $uid = trim($this->request->input('uid', '') ?? '');
        $post = null;
        if ($uid !== '') {
            $post = Post::findByUid($uid);
            if ($post === null || $post->blog_id !== $this->blog->id || !$post->isVisible()) {
                return $ok;
            }
        }

        [$device, $browser] = Hit::classifyUserAgent($userAgent);

        Hit::record(
            $this->blog,
            $post,
            Hit::identify($ip),
            Hit::normalizeReferrer($this->request->input('ref', '') ?? '', $this->blog),
            Hit::countryFromRequest($this->request),
            $device,
            $browser
        );

        return $ok;
    }

    /**
     * Riconosce i client che si dichiarano automatici. È un filtro grossolano
     * e volutamente tale: l'esclusione vera la fa il fatto che lo script parta
     * solo dopo un movimento del puntatore.
     */
    private function looksAutomated(string $userAgent): bool
    {
        if ($userAgent === '') {
            return true;
        }
        $ua = mb_strtolower($userAgent);
        foreach (['bot', 'crawler', 'spider', 'curl', 'wget', 'headless', 'python-', 'scrapy', 'monitor'] as $needle) {
            if (str_contains($ua, $needle)) {
                return true;
            }
        }
        return false;
    }
}
