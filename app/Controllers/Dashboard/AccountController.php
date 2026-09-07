<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Dashboard;

use Noblogs\Core\Auth;
use Noblogs\Core\Config;
use Noblogs\Core\I18n;
use Noblogs\Core\Mailer;
use Noblogs\Core\RateLimiter;
use Noblogs\Core\Response;
use Noblogs\Core\Session;
use Noblogs\Core\Url;
use Noblogs\Models\User;
use Noblogs\Support\Str;

/**
 * Impostazioni dell'account e cancellazione definitiva.
 */
final class AccountController extends DashboardController
{
    private const PASSWORD_MIN = 8;

    public function edit(): Response
    {
        $guard = $this->requireAuth();
        if ($guard !== null) {
            return $guard;
        }

        $user = $this->account();
        $return = Url::to('/dashboard/account');

        if ($this->request->isPost()) {
            $csrf = $this->requireCsrf();
            if ($csrf !== null) {
                return $csrf;
            }

            return match ($this->request->trimmed('azione')) {
                'email'    => $this->changeEmail($user, $return),
                'password' => $this->changePassword($user, $return),
                default    => $this->changeProfile($user, $return),
            };
        }

        return $this->panel('dashboard/account', [
            'pageTitle' => __('account.title'),
            'section'   => 'account',
            'locales'   => I18n::available(),
            'timezones' => \DateTimeZone::listIdentifiers(),
            'blogs'     => $user->blogs(),
            'minLength' => self::PASSWORD_MIN,
        ]);
    }

    public function destroy(): Response
    {
        $guard = $this->requireAuth();
        if ($guard !== null) {
            return $guard;
        }

        $csrf = $this->requireCsrf();
        if ($csrf !== null) {
            return $csrf;
        }

        $user = $this->account();
        $return = Url::to('/dashboard/account');

        if (RateLimiter::tooManyAttempts('account-delete:' . $user->id, 5, 3600)) {
            $this->flash('error', __('error.rate_limited'));
            return $this->redirect($return);
        }

        if (Auth::attempt($user->email, $this->request->input('password', '') ?? '') === null) {
            $this->flash('error', __('account.error.wrong_password'));
            return $this->redirect($return);
        }

        foreach ($user->blogs() as $blog) {
            $this->deleteBlogCompletely($blog);
        }
        $user->delete();

        // Non si distrugge la sessione: il messaggio di conferma deve
        // sopravvivere al redirect. Si scollega l'utente e si cambia
        // identificativo, così del vecchio accesso non resta niente di utile.
        Auth::logout();
        Session::regenerate();

        return $this->succeed(Url::platform('/'), __('account.deleted'));
    }

    // -----------------------------------------------------------------------
    // Interno
    // -----------------------------------------------------------------------

    private function changeProfile(User $user, string $return): Response
    {
        $locale = $this->request->trimmed('locale');
        if (!I18n::isAvailable($locale)) {
            $locale = (string) Config::get('site.locale', 'it');
        }

        $timezone = $this->request->trimmed('timezone');
        if (!in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            return $this->withInput($return, $this->request->post, __('account.error.timezone'));
        }

        $user->update([
            'locale'   => $locale,
            'timezone' => $timezone,
        ]);

        return $this->succeed($return, __('account.profile_saved'));
    }

    private function changeEmail(User $user, string $return): Response
    {
        $email = mb_strtolower($this->request->trimmed('email'));

        if (!Str::isEmail($email)) {
            return $this->withInput($return, $this->request->post, __('account.error.email_invalid'));
        }
        if ($email === $user->email) {
            return $this->withInput($return, $this->request->post, __('account.error.email_same'));
        }
        // Cambiare l'indirizzo equivale a spostare la proprietà dell'account:
        // si chiede la password come per un accesso.
        if (Auth::attempt($user->email, $this->request->input('current_password', '') ?? '') === null) {
            return $this->withInput($return, $this->request->post, __('account.error.wrong_password'));
        }
        if (User::emailTaken($email)) {
            return $this->withInput($return, $this->request->post, __('account.error.email_taken'));
        }

        $user->update(['email' => $email]);
        $token = $user->newVerifyToken();

        $sent = Mailer::send(
            $email,
            __('account.email.verify_subject', ['site' => (string) Config::get('site.name', 'Noblogs')]),
            __('account.email.verify_body', [
                'url'  => Url::platform('/verifica-email') . '?token=' . rawurlencode($token),
                'site' => (string) Config::get('site.name', 'Noblogs'),
            ])
        );

        return $this->succeed($return, $sent ? __('account.email_changed') : __('account.email_changed_no_mail'));
    }

    private function changePassword(User $user, string $return): Response
    {
        if (RateLimiter::tooManyAttempts('account-password:' . $user->id, 10, 3600)) {
            return $this->withInput($return, $this->request->post, __('error.rate_limited'));
        }

        $current = $this->request->input('current_password', '') ?? '';
        $password = $this->request->input('password', '') ?? '';
        $confirm = $this->request->input('password_confirm', '') ?? '';

        if (Auth::attempt($user->email, $current) === null) {
            return $this->withInput($return, $this->request->post, __('account.error.wrong_password'));
        }
        if (mb_strlen($password) < self::PASSWORD_MIN) {
            return $this->withInput($return, $this->request->post, __('account.error.password_short', [
                'min' => self::PASSWORD_MIN,
            ]));
        }
        if ($password !== $confirm) {
            return $this->withInput($return, $this->request->post, __('account.error.password_mismatch'));
        }

        $user->setPassword($password);
        RateLimiter::clear('account-password:' . $user->id);

        // L'identificativo di sessione cambia: se qualcuno stava usando una
        // sessione rubata, il cambio password la rende inutile.
        Session::regenerate();

        return $this->succeed($return, __('account.password_saved'));
    }
}
