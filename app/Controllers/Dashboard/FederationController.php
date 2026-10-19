<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Dashboard;

use Noblogs\Core\RateLimiter;
use Noblogs\Core\Response;
use Noblogs\Federation\ActivityPub;
use Noblogs\Federation\Reply;
use Noblogs\Models\ApNotification;

/**
 * Conversazione del blog sul fediverso: commenti in arrivo e risposte.
 */
final class FederationController extends DashboardController
{
    public function index(string $blog): Response
    {
        $found = $this->openBlog($blog);
        if ($found instanceof Response) {
            return $found;
        }

        return $this->panel('dashboard/fediverse', [
            'pageTitle'  => __('federation.inbox.title'),
            'section'    => 'fediverse',
            'blog'       => $found,
            'handle'     => ActivityPub::handle($found),
            'enabled'    => ActivityPub::blogEnabled($found),
            'instanceOn' => ActivityPub::instanceEnabled(),
            'comments'   => ApNotification::comments($found),
            'replies'    => ApNotification::repliesGrouped($found),
        ]);
    }

    public function reply(string $blog, string $id): Response
    {
        $found = $this->openBlog($blog);
        if ($found instanceof Response) {
            return $found;
        }
        $csrf = $this->requireCsrf();
        if ($csrf !== null) {
            return $csrf;
        }

        $location = $this->blogUrl($found, '/fediverso');
        if (!ActivityPub::blogEnabled($found)) {
            $this->flash('error', __('federation.reply.disabled'));
            return $this->redirect($location);
        }

        $user = $this->account();
        if (RateLimiter::tooManyAttempts('ap-reply:' . $user->id, 30, 3600)) {
            $this->flash('error', __('error.rate_limited'));
            return $this->redirect($location);
        }

        $comment = ApNotification::find((int) $id);
        if ($comment === null || $comment->blog_id !== $found->id || $comment->type !== 'comment') {
            return $this->notFound();
        }
        if ($comment->isDeleted()) {
            $this->flash('error', __('federation.reply.deleted'));
            return $this->redirect($location . '#commento-' . $comment->id);
        }

        $text = $this->request->trimmed('body');
        if ($text === '') {
            return $this->withInput(
                $location . '#commento-' . $comment->id,
                ['reply_to' => (string) $comment->id, 'body' => $text],
                __('federation.reply.empty')
            );
        }
        if (mb_strlen($text) > Reply::MAX_LENGTH) {
            return $this->withInput(
                $location . '#commento-' . $comment->id,
                ['reply_to' => (string) $comment->id, 'body' => $text],
                __('federation.reply.too_long', ['max' => Reply::MAX_LENGTH])
            );
        }

        $sent = Reply::send($found, $comment, $text);
        if ($sent === null) {
            return $this->withInput(
                $location . '#commento-' . $comment->id,
                ['reply_to' => (string) $comment->id, 'body' => $text],
                __('federation.reply.failed')
            );
        }

        $comment->markRead();

        return $this->succeed(
            $location . '#commento-' . $comment->id,
            __('federation.reply.sent')
        );
    }
}
