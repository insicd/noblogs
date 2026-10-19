<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Federation;

use Noblogs\Core\Controller;
use Noblogs\Core\Response;
use Noblogs\Federation\ActivityPub;
use Noblogs\Federation\Inbox;
use Noblogs\Models\Post;

/**
 * Actor, inbox, outbox e oggetti dei post.
 */
final class ActorController extends Controller
{
    public function actor(string $blog): Response
    {
        $found = ActivityPub::blogFromPath($blog);
        if ($found === null) {
            return Response::text('Not found', 404);
        }
        $document = ActivityPub::actorDocument($found);
        if ($document === null) {
            return Response::text('Not found', 503);
        }
        return ActivityPub::json($document)->cachePublic(300);
    }

    public function outbox(string $blog): Response
    {
        $found = ActivityPub::blogFromPath($blog);
        if ($found === null) {
            return Response::text('Not found', 404);
        }
        $page = max(0, $this->request->int('page', 0));
        return ActivityPub::json(ActivityPub::outbox($found, $page))->cachePublic(60);
    }

    public function followers(string $blog): Response
    {
        $found = ActivityPub::blogFromPath($blog);
        if ($found === null) {
            return Response::text('Not found', 404);
        }
        $page = max(0, $this->request->int('page', 0));
        return ActivityPub::json(ActivityPub::followers($found, $page))->cachePublic(60);
    }

    public function following(string $blog): Response
    {
        $found = ActivityPub::blogFromPath($blog);
        if ($found === null) {
            return Response::text('Not found', 404);
        }
        return ActivityPub::json(ActivityPub::following($found))->cachePublic(300);
    }

    public function post(string $blog, string $uid): Response
    {
        $found = ActivityPub::blogFromPath($blog);
        if ($found === null) {
            return Response::text('Not found', 404);
        }
        $post = Post::findByUid($uid);
        if ($post === null || $post->blog_id !== $found->id || !$post->isVisible() || $post->is_page) {
            return Response::text('Not found', 404);
        }
        return ActivityPub::json(ActivityPub::articleDocument($found, $post))->cachePublic(60);
    }

    public function inbox(string $blog): Response
    {
        $found = ActivityPub::blogFromPath($blog);
        if ($found === null) {
            return Response::text('Not found', 404);
        }
        $status = Inbox::handle($this->request, $found);
        return ActivityPub::json($status >= 400 ? ['error' => 'rejected'] : ['ok' => true], $status);
    }

    public function sharedInbox(): Response
    {
        if (!ActivityPub::instanceEnabled()) {
            return Response::text('Not found', 404);
        }
        $status = Inbox::handle($this->request, null);
        return ActivityPub::json($status >= 400 ? ['error' => 'rejected'] : ['ok' => true], $status);
    }
}
