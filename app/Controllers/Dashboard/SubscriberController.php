<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Dashboard;

use Noblogs\Core\Response;
use Noblogs\Models\Subscriber;

/**
 * Indirizzi iscritti agli aggiornamenti di un blog.
 *
 * Noblogs raccoglie e conferma gli indirizzi, e li restituisce in CSV: l'invio
 * delle newsletter resta fuori, perché una piattaforma che spedisce campagne è
 * una piattaforma che prima o poi le spedisce per conto di uno spammer.
 */
final class SubscriberController extends DashboardController
{
    public function index(string $blog): Response
    {
        $found = $this->openBlog($blog);
        if ($found instanceof Response) {
            return $found;
        }

        if ($this->request->isPost()) {
            $csrf = $this->requireCsrf();
            if ($csrf !== null) {
                return $csrf;
            }

            $subscriber = Subscriber::find($this->request->int('id'));
            if ($subscriber === null || $subscriber->blog_id !== $found->id) {
                return $this->notFound(__('subscribers.not_found'));
            }

            $email = $subscriber->email;
            $subscriber->delete();

            return $this->succeed(
                $this->blogUrl($found, '/iscritti'),
                __('subscribers.removed', ['email' => $email])
            );
        }

        if ($this->request->query('esporta') === 'csv') {
            return Response::download(
                Subscriber::exportCsv($found),
                'iscritti-' . $found->subdomain . '-' . gmdate('Y-m-d') . '.csv',
                'text/csv; charset=UTF-8'
            )->noCache();
        }

        $confirmed = Subscriber::forBlog($found);

        return $this->panel('dashboard/subscribers', [
            'pageTitle'   => __('subscribers.title'),
            'section'     => 'subscribers',
            'blog'        => $found,
            'subscribers' => $confirmed,
            'confirmed'   => count($confirmed),
            'pending'     => Subscriber::countForBlog($found, false) - count($confirmed),
        ]);
    }
}
