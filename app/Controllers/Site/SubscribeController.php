<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Site;

use Noblogs\Core\Csrf;
use Noblogs\Core\Mailer;
use Noblogs\Core\RateLimiter;
use Noblogs\Core\Response;
use Noblogs\Core\Url;
use Noblogs\Models\Subscriber;
use Noblogs\Support\Str;

/**
 * Iscrizione agli aggiornamenti di un blog.
 *
 * Con conferma via email: senza, chiunque potrebbe iscrivere l'indirizzo di
 * qualcun altro, e una lista di recapiti altrui è un problema, non una
 * funzionalità.
 */
final class SubscribeController extends SiteController
{
    public function index(): Response
    {
        if (!$this->blog->subscriptions_active) {
            return $this->notFound();
        }

        if ($this->request->isPost()) {
            return $this->store();
        }

        return $this->page('site/subscribe', [
            'bodyClass' => 'subscribe',
            'pageTitle' => __('subscribe.title') . ' — ' . $this->blog->title,
            'indexable' => false,
            'trackPath' => null,
        ]);
    }

    private function store(): Response
    {
        $email = trim($this->request->input('email', '') ?? '');

        // Il campo esca è nascosto via CSS e non ha ragione di essere pieno.
        if (trim($this->request->input('website', '') ?? '') !== '') {
            return $this->confirmationSent();
        }

        // Il modulo porta un token firmato: senza, la richiesta non è passata
        // da una pagina del blog.
        if (!Csrf::verifySigned('subscribe', $this->request->input('ts', '') ?? '')) {
            return $this->confirmationSent();
        }

        $ip = $this->request->ip();
        if (RateLimiter::tooManyAttempts('subscribe:' . RateLimiter::hashIp($ip), 5, 3600)
            || RateLimiter::tooManyAttempts('subscribe-blog:' . $this->blog->id, 20, 600)) {
            return $this->confirmationSent();
        }

        if (!Str::isEmail($email)) {
            return $this->page('site/subscribe', [
                'bodyClass' => 'subscribe',
                'pageTitle' => __('subscribe.title') . ' — ' . $this->blog->title,
                'error'     => __('subscribe.invalid_email'),
                'indexable' => false,
                'trackPath' => null,
            ], 422);
        }

        $subscriber = Subscriber::subscribe($this->blog, $email);
        if ($subscriber !== null) {
            $this->sendConfirmation($subscriber);
        }

        // La risposta è identica sia che l'indirizzo fosse già iscritto sia che
        // non lo fosse: la pagina non deve rivelare chi segue questo blog.
        return $this->confirmationSent();
    }

    public function confirm(): Response
    {
        $subscriber = Subscriber::findByToken(trim($this->request->query('token') ?? ''));

        if ($subscriber === null || $subscriber->blog_id !== $this->blog->id) {
            return $this->notFound(__('subscribe.invalid_link'));
        }

        $subscriber->confirm();

        return $this->page('site/subscribe', [
            'bodyClass' => 'subscribe',
            'pageTitle' => __('subscribe.confirmed_title') . ' — ' . $this->blog->title,
            'state'     => 'confirmed',
            'indexable' => false,
            'trackPath' => null,
        ]);
    }

    public function unsubscribe(): Response
    {
        $subscriber = Subscriber::findByToken(trim($this->request->query('token') ?? ''));

        if ($subscriber !== null && $subscriber->blog_id === $this->blog->id) {
            $subscriber->delete();
        }

        // Anche con un token inesistente si conferma la cancellazione: chi
        // vuole andarsene non deve incontrare ostacoli o messaggi d'errore.
        return $this->page('site/subscribe', [
            'bodyClass' => 'subscribe',
            'pageTitle' => __('subscribe.unsubscribed_title') . ' — ' . $this->blog->title,
            'state'     => 'unsubscribed',
            'indexable' => false,
            'trackPath' => null,
        ]);
    }

    private function confirmationSent(): Response
    {
        return $this->page('site/subscribe', [
            'bodyClass' => 'subscribe',
            'pageTitle' => __('subscribe.title') . ' — ' . $this->blog->title,
            'state'     => 'pending',
            'indexable' => false,
            'trackPath' => null,
        ]);
    }

    private function sendConfirmation(Subscriber $subscriber): void
    {
        $confirmUrl = Url::blog($this->blog, '/conferma-iscrizione/') . '?token=' . $subscriber->token;

        Mailer::send(
            $subscriber->email,
            __('subscribe.email_subject', ['blog' => $this->blog->title]),
            __('subscribe.email_body', [
                'blog' => $this->blog->title,
                'url'  => $confirmUrl,
            ])
        );
    }
}
