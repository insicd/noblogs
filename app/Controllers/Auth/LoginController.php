<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Auth;

use Noblogs\Core\Auth;
use Noblogs\Core\Controller;
use Noblogs\Core\Csrf;
use Noblogs\Core\RateLimiter;
use Noblogs\Core\Response;
use Noblogs\Core\Session;
use Noblogs\Core\Url;

/**
 * Accesso e uscita.
 *
 * Il messaggio di errore è uno solo — «email o password non corretti» — sia
 * quando l'indirizzo non esiste, sia quando la password è sbagliata, sia
 * quando l'account è stato disattivato: la pagina di accesso non deve poter
 * essere usata come elenco degli iscritti.
 */
final class LoginController extends Controller
{
    /** Tentativi consentiti per uno stesso indirizzo email. */
    private const EMAIL_ATTEMPTS = 5;

    /**
     * Tentativi consentiti da uno stesso IP. Il tetto è più alto di quello per
     * indirizzo perché dietro un solo IP può esserci un intero ufficio: con
     * cinque tentativi si bloccherebbero tutti insieme.
     */
    private const IP_ATTEMPTS = 25;

    private const WINDOW = 900;

    public function login(): Response
    {
        if ($this->request->isPost()) {
            return $this->attempt();
        }

        if (Auth::check()) {
            return $this->redirect(Url::to($this->destination()));
        }

        return $this->view('auth/login', [
            'pageTitle'   => __('auth.login.title'),
            'bodyClass'   => 'auth',
            'indexable'   => false,
            'next'        => $this->nextPath(),
            'old'         => Session::takeOldInput(),
        ])->noCache()->noIndex();
    }

    public function logout(): Response
    {
        if (($stop = $this->requireCsrf()) !== null) {
            return $stop;
        }

        Auth::logout();
        // Nuovo identificativo di sessione: quello vecchio è stato associato a
        // un utente autenticato e non deve poter essere riutilizzato.
        Session::regenerate();
        $this->flash('success', __('auth.logout.done'));

        return $this->redirect(Url::to('/'))->noCache();
    }

    private function attempt(): Response
    {
        if (($stop = $this->requireCsrf()) !== null) {
            return $stop;
        }

        $email = mb_strtolower($this->request->trimmed('email'));
        $password = $this->request->input('password', '') ?? '';
        $next = $this->nextPath();
        $formUrl = Url::to('/accedi') . ($next !== '' ? '?next=' . rawurlencode($next) : '');

        $ipKey = 'login-ip:' . RateLimiter::hashIp($this->request->ip());
        $emailKey = 'login-email:' . self::emailBucket($email);

        // Entrambi i contatori vanno incrementati a ogni tentativo: valutarli
        // in corto circuito lascerebbe fermo uno dei due.
        $ipBlocked = RateLimiter::tooManyAttempts($ipKey, self::IP_ATTEMPTS, self::WINDOW);
        $emailBlocked = RateLimiter::tooManyAttempts($emailKey, self::EMAIL_ATTEMPTS, self::WINDOW);

        if ($ipBlocked || $emailBlocked) {
            return $this->withInput($formUrl, ['email' => $email], __('error.rate_limited'));
        }

        $user = Auth::attempt($email, $password);
        if ($user === null) {
            return $this->withInput($formUrl, ['email' => $email], __('auth.login.failed'));
        }

        Auth::login($user);
        RateLimiter::clear($emailKey);
        RateLimiter::clear($ipKey);
        $this->flash('success', __('auth.login.welcome'));

        return $this->redirect(Url::to($this->destination()))->noCache();
    }

    private function destination(): string
    {
        $next = $this->nextPath();

        return $next !== '' ? $next : '/dashboard';
    }

    /**
     * Percorso di destinazione richiesto con ?next=, accettato solo se è
     * interno: `//altro.sito` e `/\altro.sito` sono trattati dai browser come
     * indirizzi assoluti e permetterebbero di usare la pagina di accesso come
     * trampolino verso un sito ostile.
     */
    private function nextPath(): string
    {
        $next = $this->request->input('next', '') ?? '';

        if ($next === '' || mb_strlen($next) > 500) {
            return '';
        }
        if ($next[0] !== '/' || str_starts_with($next, '//') || str_starts_with($next, '/\\')) {
            return '';
        }
        if (preg_match('/[\x00-\x1f\x7f]/', $next) === 1) {
            return '';
        }

        return $next;
    }

    private static function emailBucket(string $email): string
    {
        // Nel secchiello non finisce l'indirizzo in chiaro: la tabella dei
        // limiti non deve diventare un elenco di email tentate.
        return substr(Csrf::hmac('login:' . $email), 0, 32);
    }
}
