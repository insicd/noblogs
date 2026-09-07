<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Auth;

use Noblogs\Core\Config;
use Noblogs\Core\Controller;
use Noblogs\Core\Mailer;
use Noblogs\Core\RateLimiter;
use Noblogs\Core\Response;
use Noblogs\Core\Session;
use Noblogs\Core\Url;
use Noblogs\Models\User;
use Noblogs\Support\Str;

/**
 * Recupero della password.
 *
 * La richiesta risponde sempre nello stesso modo, che l'indirizzo esista o no:
 * un modulo che dicesse «indirizzo sconosciuto» sarebbe un modo comodo per
 * verificare quali email sono registrate qui.
 */
final class PasswordController extends Controller
{
    private const REQUEST_PER_IP = 10;
    private const REQUEST_PER_EMAIL = 3;
    private const RESET_PER_IP = 20;

    // -----------------------------------------------------------------------
    // Richiesta del link
    // -----------------------------------------------------------------------

    public function request(): Response
    {
        if ($this->request->isPost()) {
            return $this->sendLink();
        }

        return $this->view('auth/password-request', [
            'pageTitle' => __('auth.password.request_title'),
            'bodyClass' => 'auth',
            'indexable' => false,
            'old'       => Session::takeOldInput(),
        ])->noCache()->noIndex();
    }

    private function sendLink(): Response
    {
        if (($stop = $this->requireCsrf()) !== null) {
            return $stop;
        }

        $email = mb_strtolower($this->request->trimmed('email'));

        $ipLimited = RateLimiter::tooManyAttempts(
            'password-ip:' . RateLimiter::hashIp($this->request->ip()),
            self::REQUEST_PER_IP,
            3600
        );
        $emailLimited = $email !== '' && RateLimiter::tooManyAttempts(
            'password-email:' . RegisterController::emailBucket($email),
            self::REQUEST_PER_EMAIL,
            3600
        );

        if (!$ipLimited && !$emailLimited && Str::isEmail($email)) {
            $user = User::findByEmail($email);
            // Un account disattivato non riceve il link: reimpostare la
            // password non lo riattiverebbe comunque.
            if ($user !== null && $user->is_active) {
                $this->sendResetEmail($user);
            }
        }

        // Sempre lo stesso messaggio, anche quando il limite è stato superato.
        $this->flash('success', __('auth.password.sent'));

        return $this->redirect(Url::to('/password/dimenticata'))->noCache();
    }

    // -----------------------------------------------------------------------
    // Nuova password
    // -----------------------------------------------------------------------

    public function reset(): Response
    {
        // Il limite qui difende dai tentativi di indovinare un token: sono
        // 48 caratteri esadecimali, ma il costo di provare deve restare alto.
        if (RateLimiter::tooManyAttempts(
            'password-reset-ip:' . RateLimiter::hashIp($this->request->ip()),
            self::RESET_PER_IP,
            900
        )) {
            $this->flash('error', __('error.rate_limited'));
            return $this->redirect(Url::to('/password/dimenticata'))->noCache();
        }

        $token = trim($this->request->input('token', '') ?? '');
        $user = User::findByResetToken($token);

        if ($user === null) {
            return $this->view('auth/password-reset', [
                'pageTitle' => __('auth.password.invalid_title'),
                'bodyClass' => 'auth',
                'indexable' => false,
                'state'     => 'invalid',
                'token'     => '',
            ], 410)->noCache()->noIndex();
        }

        if (!$this->request->isPost()) {
            return $this->view('auth/password-reset', [
                'pageTitle' => __('auth.password.reset_title'),
                'bodyClass' => 'auth',
                'indexable' => false,
                'state'     => 'form',
                'token'     => $token,
            ])->noCache()->noIndex();
        }

        if (($stop = $this->requireCsrf()) !== null) {
            return $stop;
        }

        $password = $this->request->input('password', '') ?? '';
        $error = RegisterController::passwordError($password, $user->email);
        if ($error !== null) {
            $this->flash('error', $error);
            return $this->redirect(Url::to('/password/reimposta') . '?token=' . rawurlencode($token))->noCache();
        }

        // setPassword() azzera anche token e scadenza: il link vale una volta.
        $user->setPassword($password);
        $this->flash('success', __('auth.password.reset_done'));

        return $this->redirect(Url::to('/accedi'))->noCache();
    }

    // -----------------------------------------------------------------------
    // Supporto
    // -----------------------------------------------------------------------

    private function sendResetEmail(User $user): void
    {
        $token = $user->startPasswordReset();
        $url = Url::platform('/password/reimposta') . '?token=' . rawurlencode($token);

        Mailer::send(
            $user->email,
            __('auth.password.email_subject', ['site' => $this->siteName()]),
            __('auth.password.email_body', [
                'site' => $this->siteName(),
                'url'  => $url,
            ])
        );
    }

    private function siteName(): string
    {
        return (string) Config::get('site.name', 'Noblogs');
    }
}
