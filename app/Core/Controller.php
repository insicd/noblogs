<?php

declare(strict_types=1);

namespace Noblogs\Core;

use Noblogs\Models\User;

/**
 * Base dei controller: accesso alla richiesta, al tenant e alle scorciatoie di
 * risposta più usate.
 */
abstract class Controller
{
    public function __construct(
        protected Request $request,
        protected Tenant $tenant,
    ) {
    }

    protected function user(): ?User
    {
        return Auth::user();
    }

    /** @param array<string,mixed> $data */
    protected function view(string $template, array $data = [], int $status = 200): Response
    {
        return View::response($template, $data + ['tenant' => $this->tenant], $status);
    }

    protected function redirect(string $location, int $status = 302): Response
    {
        return Response::redirect($location, $status);
    }

    protected function back(string $fallback = '/'): Response
    {
        $referrer = $this->request->referrer();
        $host = $referrer !== '' ? parse_url($referrer, PHP_URL_HOST) : null;
        // Si torna indietro solo se il riferimento è interno: un Referer
        // esterno non deve poter pilotare la destinazione.
        if (is_string($host) && $host === $this->request->host) {
            return $this->redirect($referrer);
        }
        return $this->redirect($fallback);
    }

    protected function flash(string $type, string $message): void
    {
        Session::flash($type, $message);
    }

    /** Blocca le richieste POST prive di un token CSRF valido. */
    protected function requireCsrf(): ?Response
    {
        if (!$this->request->isPost()) {
            return null;
        }
        if (Csrf::check($this->request)) {
            return null;
        }
        $this->flash('error', __('error.csrf'));
        return $this->back();
    }

    protected function notFound(string $message = ''): Response
    {
        return Response::html(
            View::make('errors/404', ['message' => $message, 'tenant' => $this->tenant]),
            404
        );
    }

    protected function forbidden(string $message = ''): Response
    {
        return Response::html(
            View::make('errors/403', ['message' => $message, 'tenant' => $this->tenant]),
            403
        );
    }

    /** @param array<string,mixed> $input */
    protected function withInput(string $location, array $input, string $error = ''): Response
    {
        if ($error !== '') {
            $this->flash('error', $error);
        }
        unset($input['_token'], $input['password'], $input['password_confirm']);
        Session::flashInput($input);
        return $this->redirect($location);
    }
}
