<?php

declare(strict_types=1);

namespace Noblogs\Federation;

use Noblogs\Core\Database;
use Noblogs\Core\Url;
use Noblogs\Models\ApFollower;
use Noblogs\Models\ApNotification;
use Noblogs\Models\Blog;
use Noblogs\Models\Post;
use Noblogs\Support\Str;

/**
 * Risposta dell'autore a un commento arrivato dal fediverso.
 *
 * È una Note ActivityPub inReplyTo l'oggetto remoto, consegnata all'inbox
 * di chi ha commentato e ai follower del blog.
 */
final class Reply
{
    public const MAX_LENGTH = 5000;

    public static function send(Blog $blog, ApNotification $comment, string $text): ?ApNotification
    {
        if (!ActivityPub::blogEnabled($blog) || $comment->type !== 'comment' || $comment->isDeleted()) {
            return null;
        }
        $inReplyTo = trim((string) $comment->object_id);
        if ($inReplyTo === '') {
            return null;
        }

        $text = trim($text);
        if ($text === '' || mb_strlen($text) > self::MAX_LENGTH) {
            return null;
        }

        Schema::ensure();
        $uid = Str::uid(16);
        $objectId = ActivityPub::replyId($blog, $uid);
        $published = ActivityPub::iso(gmdate('Y-m-d H:i:s'));
        $html = self::toHtml($text);
        $to = [ActivityPub::PUBLIC, $comment->actor_id];
        $cc = [ActivityPub::followersUrl($blog)];
        $mentionName = $comment->handle !== null && $comment->handle !== ''
            ? '@' . ltrim($comment->handle, '@')
            : $comment->actor_id;

        $object = [
            'id' => $objectId,
            'type' => 'Note',
            'published' => $published,
            'attributedTo' => ActivityPub::actorId($blog),
            'inReplyTo' => $inReplyTo,
            'content' => $html,
            'mediaType' => 'text/html',
            'to' => $to,
            'cc' => $cc,
            'tag' => [[
                'type' => 'Mention',
                'href' => $comment->actor_id,
                'name' => $mentionName,
            ]],
        ];
        $post = $comment->post();
        if ($post instanceof Post) {
            $object['url'] = Url::post($blog, $post->slug);
        }

        $activity = [
            '@context' => ActivityPub::CONTEXT,
            'id' => $objectId . '/activity',
            'type' => 'Create',
            'actor' => ActivityPub::actorId($blog),
            'published' => $published,
            'to' => $to,
            'cc' => $cc,
            'object' => $object,
        ];

        $inboxes = self::targetInboxes($blog, $comment);
        if ($inboxes === []) {
            return null;
        }

        Delivery::enqueueTo($blog, $activity, $inboxes);
        Delivery::processDue(8);

        ApNotification::record($blog, [
            'type'         => 'reply',
            'post_id'      => $comment->post_id,
            'parent_id'    => $comment->id,
            'actor_id'     => ActivityPub::actorId($blog),
            'handle'       => ActivityPub::handle($blog),
            'display_name' => $blog->title,
            'content'      => $text,
            'activity_id'  => $objectId . '/activity',
            'object_id'    => $objectId,
            'is_read'      => true,
        ]);

        return ApNotification::findByObject($blog, $objectId);
    }

    /** @return array<string,mixed>|null */
    public static function document(Blog $blog, string $uid): ?array
    {
        $note = ApNotification::findByObject($blog, ActivityPub::replyId($blog, $uid));
        if ($note === null || $note->type !== 'reply') {
            return null;
        }

        $parent = $note->parent_id !== null ? ApNotification::find($note->parent_id) : null;
        $inReplyTo = $parent !== null ? (string) $parent->object_id : '';
        $to = [ActivityPub::PUBLIC];
        if ($parent !== null && $parent->actor_id !== '') {
            $to[] = $parent->actor_id;
        }

        $document = [
            '@context' => ActivityPub::CONTEXT,
            'id' => (string) $note->object_id,
            'type' => 'Note',
            'published' => ActivityPub::iso($note->created_at),
            'attributedTo' => ActivityPub::actorId($blog),
            'content' => self::toHtml((string) $note->content),
            'mediaType' => 'text/html',
            'to' => $to,
            'cc' => [ActivityPub::followersUrl($blog)],
        ];
        if ($inReplyTo !== '') {
            $document['inReplyTo'] = $inReplyTo;
        }

        return $document;
    }

    /**
     * @return list<string>
     */
    private static function targetInboxes(Blog $blog, ApNotification $comment): array
    {
        $inboxes = ApFollower::inboxes($blog);
        $direct = self::inboxForActor($comment->actor_id);
        if ($direct !== null) {
            array_unshift($inboxes, $direct);
        }
        return $inboxes;
    }

    private static function inboxForActor(string $actorId): ?string
    {
        Schema::ensure();
        $row = Database::instance()->fetch(
            'SELECT inbox_url, shared_inbox_url FROM {{ap_remote_actors}} WHERE actor_id = ?',
            [$actorId]
        );
        if (is_array($row)) {
            $inbox = (string) ($row['inbox_url'] ?? '');
            $shared = (string) ($row['shared_inbox_url'] ?? '');
            if (Http::isSafeUrl($inbox)) {
                return $inbox;
            }
            if (Http::isSafeUrl($shared)) {
                return $shared;
            }
        }

        $document = Http::getJson($actorId);
        if (!is_array($document)) {
            return null;
        }
        $inbox = (string) ($document['inbox'] ?? '');
        return Http::isSafeUrl($inbox) ? $inbox : null;
    }

    public static function toHtml(string $text): string
    {
        $text = str_replace("\r\n", "\n", trim($text));
        if ($text === '') {
            return '<p></p>';
        }
        $blocks = preg_split("/\n{2,}/", $text) ?: [$text];
        $html = '';
        foreach ($blocks as $block) {
            $html .= '<p>' . nl2br(htmlspecialchars($block, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), false) . '</p>';
        }
        return $html;
    }
}
