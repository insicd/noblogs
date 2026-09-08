<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Auth;

use Noblogs\Core\Auth;
use Noblogs\Core\Config;
use Noblogs\Core\Controller;
use Noblogs\Core\Csrf;
use Noblogs\Core\Database;
use Noblogs\Core\ErrorHandler;
use Noblogs\Core\Mailer;
use Noblogs\Core\RateLimiter;
use Noblogs\Core\Response;
use Noblogs\Core\Session;
use Noblogs\Core\Url;
use Noblogs\Models\Blog;
use Noblogs\Models\User;
use Noblogs\Support\Str;

/**
 * Registrazione in un unico passaggio.
 *
 * Account e primo blog nascono insieme, dentro la stessa transazione: il
 * percorso più corto verso «ho un blog» è quello che porta più persone a
 * scrivere davvero, e un account senza blog non serve a nessuno.
 *
 * Se l'indirizzo email è già registrato la pagina risponde esattamente come
 * per un indirizzo nuovo, e la notizia del tentativo va per email a chi
 * possiede quell'indirizzo: chi compila il modulo non impara nulla.
 */
final class RegisterController extends Controller
{
    public const MIN_PASSWORD = 10;

    /** Oltre questa soglia la password è solo un peso per il server. */
    public const MAX_PASSWORD = 200;

    private const MAX_BLOG_TITLE = 200;

    /** Chiave di sessione con l'indirizzo in attesa di verifica. */
    private const PENDING = '_pending_email';

    /**
     * Password troppo diffuse per essere accettate. Elenco corto e volutamente
     * tale: sono le voci che compaiono in testa a ogni raccolta di credenziali
     * trafugate, cioè quelle che un attacco prova per prime. La difesa vera è
     * la lunghezza minima, non l'elenco.
     */
    private const WEAK_PASSWORDS = [
        'password12', 'password123', 'password1234', 'password2024', 'password2025',
        'passw0rd123', 'p@ssword123', 'passwordpassword', 'motdepasse',
        '1234567890', '12345678901', '123456789012', '0123456789', '1234512345',
        '1111111111', '0000000000', 'qwertyuiop', 'qwerty12345', 'qwertyuiop123',
        'asdfghjkl1', 'zxcvbnm123', 'administrator', 'amministratore',
        'iloveyou123', 'letmein1234', 'welcome12345', 'welcome1234',
        'trustno1234', 'sunshine123', 'princess123', 'football123',
        'baseball123', 'dragon12345', 'monkey12345', 'shadow12345',
        'master12345', 'superman123', 'batman12345', 'michael1234',
        'jennifer123', 'jessica1234', 'ciaociao123', 'amoreamore1',
        'juventus123', 'liverpool123', 'francesco12', 'alessandro1',
        'giuseppe123', 'benvenuto123', 'segretissima', 'noblogs1234',
    ];

    // -----------------------------------------------------------------------
    // Registrazione
    // -----------------------------------------------------------------------

    public function register(): Response
    {
        if (Auth::check()) {
            return $this->redirect(Url::to('/dashboard'));
        }

        if ($this->request->isPost()) {
            return $this->store();
        }

        return $this->view('auth/register', [
            'pageTitle' => __('auth.register.title'),
            'bodyClass' => 'auth',
            'indexable' => true,
            // Il modulo rapido della pagina di ingresso arriva qui con
            // l'indirizzo del blog nella query string: si usa come valore
            // iniziale, a meno che ci sia un tentativo precedente da ripristinare.
            'old'       => Session::takeOldInput() + ['subdomain' => mb_strtolower($this->request->trimmed('subdomain'))],
            'domain'    => Url::mainDomain(),
        ])->noCache();
    }

    private function store(): Response
    {
        if (($stop = $this->requireCsrf()) !== null) {
            return $stop;
        }

        $formUrl = Url::to('/registrati');

        // Campo esca: invisibile nel modulo, non ha motivo di essere pieno.
        // Chi lo compila riceve la stessa pagina di successo e nient'altro.
        if ($this->request->trimmed('website') !== '') {
            return $this->registrationAccepted();
        }

        $ip = $this->request->ip();
        if (RateLimiter::tooManyAttempts('register-ip:' . RateLimiter::hashIp($ip), 5, 3600)) {
            return $this->withInput($formUrl, $this->request->post, __('error.rate_limited'));
        }

        $email = mb_strtolower($this->request->trimmed('email'));
        $password = $this->request->input('password', '') ?? '';
        $subdomain = mb_strtolower($this->request->trimmed('subdomain'));
        $title = $this->request->trimmed('title');

        $errors = [];

        if ($email === '') {
            $errors[] = __('auth.error.email_required');
        } elseif (!Str::isEmail($email)) {
            $errors[] = __('auth.error.email_invalid');
        }

        $passwordError = self::passwordError($password, $email, $subdomain);
        if ($passwordError !== null) {
            $errors[] = $passwordError;
        }

        // L'indirizzo del blog è un dato pubblico: dire che è già occupato non
        // rivela nulla, e tacerlo renderebbe il modulo inutilizzabile.
        $subdomainError = Blog::validateSubdomain($subdomain);
        if ($subdomainError !== null) {
            $errors[] = $subdomainError;
        }

        if ($title === '') {
            $errors[] = __('auth.error.title_required');
        } elseif (mb_strlen($title) > self::MAX_BLOG_TITLE) {
            $errors[] = __('auth.error.title_long', ['max' => self::MAX_BLOG_TITLE]);
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->flash('error', $error);
            }
            return $this->withInput($formUrl, $this->request->post);
        }

        // Limite per indirizzo: oltre la soglia non si crea nulla e non si
        // manda nessun messaggio, ma la pagina è la stessa di sempre.
        if (RateLimiter::tooManyAttempts('register-email:' . self::emailBucket($email), 3, 3600)) {
            return $this->registrationAccepted($email);
        }

        // Indirizzo già registrato: non si crea nulla, si avvisa chi possiede
        // l'indirizzo e si risponde con la stessa pagina di successo.
        if (User::emailTaken($email)) {
            $this->warnExistingAccount($email);
            return $this->registrationAccepted($email);
        }

        try {
            /** @var array{0:User,1:Blog} $created */
            $created = Database::instance()->transaction(
                static function () use ($email, $password, $subdomain, $title): array {
                    $user = User::create($email, $password);
                    $blog = Blog::create(
                        $user,
                        $subdomain,
                        $title,
                        Blog::starterContent($title, $user->locale)
                    );
                    return [$user, $blog];
                }
            );
        } catch (\PDOException $e) {
            // Corsa fra due registrazioni sullo stesso indirizzo di blog: il
            // vincolo di unicità del database è l'ultima parola.
            ErrorHandler::log($e);
            return $this->withInput($formUrl, $this->request->post, __('blog.error.subdomain_taken'));
        }

        [$user, $blog] = $created;
        $blog->notifyPendingReview($user);

        if ($user->verify_token !== null) {
            $this->sendVerification($user, $blog);
            return $this->registrationAccepted($email);
        }

        // Verifica dell'email disattivata nella configurazione: l'account è
        // già utilizzabile e non c'è nessun messaggio da attendere.
        $this->flash('success', __('auth.register.created_ready'));

        return $this->redirect(Url::to('/accedi'))->noCache();
    }

    // -----------------------------------------------------------------------
    // Verifica dell'indirizzo email
    // -----------------------------------------------------------------------

    public function verify(): Response
    {
        $token = trim($this->request->query('token') ?? '');

        if ($token === '') {
            return $this->pendingPage();
        }

        $user = User::findByVerifyToken($token);
        if ($user === null) {
            return $this->view('auth/verify', [
                'pageTitle' => __('auth.verify.invalid_title'),
                'bodyClass' => 'auth',
                'indexable' => false,
                'state'     => 'invalid',
                'email'     => '',
            ], 404)->noCache()->noIndex();
        }

        $user->markEmailVerified();
        Session::forget(self::PENDING);
        $this->flash('success', __('auth.verify.done'));

        return $this->redirect(Url::to('/accedi'))->noCache();
    }

    public function resend(): Response
    {
        if (($stop = $this->requireCsrf()) !== null) {
            return $stop;
        }

        $pending = Session::get(self::PENDING);
        $email = is_string($pending) ? $pending : mb_strtolower($this->request->trimmed('email'));

        $ipLimited = RateLimiter::tooManyAttempts(
            'verify-resend-ip:' . RateLimiter::hashIp($this->request->ip()),
            5,
            3600
        );
        $emailLimited = $email !== '' && RateLimiter::tooManyAttempts(
            'verify-resend:' . self::emailBucket($email),
            3,
            3600
        );

        if (!$ipLimited && !$emailLimited && Str::isEmail($email)) {
            $user = User::findByEmail($email);
            if ($user !== null && $user->verify_token !== null && $user->email_verified_at === null) {
                $user->newVerifyToken();
                $blogs = $user->blogs();
                $this->sendVerification($user, $blogs[0] ?? null);
            }
        }

        // Messaggio identico in tutti i casi, limite di frequenza compreso:
        // altrimenti basterebbe questo modulo per sapere chi è registrato.
        $this->flash('success', __('auth.verify.resent'));

        return $this->redirect(Url::to('/verifica-email'))->noCache();
    }

    // -----------------------------------------------------------------------
    // Validazione della password, condivisa con PasswordController
    // -----------------------------------------------------------------------

    /**
     * Controlla la robustezza minima di una password.
     *
     * @param string $email     Indirizzo dell'account, per rifiutare le password che lo contengono.
     * @param string $subdomain Indirizzo del blog, per lo stesso motivo.
     * @return string|null Messaggio d'errore, oppure null se la password va bene.
     */
    public static function passwordError(string $password, string $email = '', string $subdomain = ''): ?string
    {
        if ($password === '') {
            return __('auth.error.password_required');
        }

        $length = mb_strlen($password);
        if ($length < self::MIN_PASSWORD) {
            return __('auth.error.password_short', ['min' => self::MIN_PASSWORD]);
        }
        if ($length > self::MAX_PASSWORD) {
            return __('auth.error.password_long', ['max' => self::MAX_PASSWORD]);
        }

        $normalized = mb_strtolower(trim($password));

        if (in_array($normalized, self::WEAK_PASSWORDS, true)) {
            return __('auth.error.password_weak');
        }

        // Un solo carattere ripetuto, oppure una corsa sulla tastiera o
        // sull'alfabeto: la lunghezza c'è, l'imprevedibilità no.
        if (preg_match('/^(.)\1+$/u', $normalized) === 1) {
            return __('auth.error.password_weak');
        }
        foreach (['01234567890123456789', 'abcdefghijklmnopqrstuvwxyz', 'qwertyuiopasdfghjklzxcvbnm'] as $run) {
            if (str_contains($run, $normalized) || str_contains(strrev($run), $normalized)) {
                return __('auth.error.password_weak');
            }
        }

        $local = $email !== '' ? mb_strtolower((string) strstr($email, '@', true)) : '';
        if ($local !== '' && mb_strlen($local) >= 4 && str_contains($normalized, $local)) {
            return __('auth.error.password_weak');
        }
        if ($subdomain !== '' && mb_strlen($subdomain) >= 4 && str_contains($normalized, mb_strtolower($subdomain))) {
            return __('auth.error.password_weak');
        }

        return null;
    }

    public static function emailBucket(string $email): string
    {
        return substr(Csrf::hmac('email:' . mb_strtolower($email)), 0, 32);
    }

    // -----------------------------------------------------------------------
    // Supporto
    // -----------------------------------------------------------------------

    /**
     * Esito di una registrazione andata a buon fine — o di una che non è
     * avvenuta perché l'indirizzo era già registrato, o perché il campo esca
     * era pieno. Le tre situazioni sono indistinguibili dall'esterno.
     */
    private function registrationAccepted(string $email = ''): Response
    {
        if ($email !== '') {
            Session::put(self::PENDING, $email);
        }
        $this->flash('success', __('auth.register.created'));

        return $this->redirect(Url::to('/verifica-email'))->noCache();
    }

    private function pendingPage(): Response
    {
        $pending = Session::get(self::PENDING);

        return $this->view('auth/verify', [
            'pageTitle' => __('auth.verify.title'),
            'bodyClass' => 'auth',
            'indexable' => false,
            'state'     => 'sent',
            'email'     => is_string($pending) ? $pending : '',
        ])->noCache()->noIndex();
    }

    private function sendVerification(User $user, ?Blog $blog): void
    {
        if ($user->verify_token === null) {
            return;
        }

        $url = Url::platform('/verifica-email') . '?token=' . rawurlencode($user->verify_token);

        Mailer::send(
            $user->email,
            __('auth.verify.email_subject', ['site' => self::siteName()]),
            __('auth.verify.email_body', [
                'site' => self::siteName(),
                'blog' => $blog !== null ? Url::blogRoot($blog) : Url::platform('/dashboard'),
                'url'  => $url,
            ])
        );
    }

    private function warnExistingAccount(string $email): void
    {
        Mailer::send(
            $email,
            __('auth.register.exists_subject'),
            __('auth.register.exists_body', [
                'site'  => self::siteName(),
                'login' => Url::platform('/accedi'),
                'reset' => Url::platform('/password/dimenticata'),
            ])
        );
    }

    private static function siteName(): string
    {
        return (string) Config::get('site.name', 'Noblogs');
    }
}
