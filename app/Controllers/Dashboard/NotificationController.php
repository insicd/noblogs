<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Dashboard;

use Noblogs\Core\Response;
use Noblogs\Federation\ActivityPub;
use Noblogs\Federation\Delivery;
use Noblogs\Models\ApFollower;
use Noblogs\Models\ApNotification;

/**
 * Notifiche dal fediverso: follower e commenti sui post.
 */
final class NotificationController extends DashboardController
{
    public function index(string $blog): Response
    {
        $found = $this->openBlog($blog);
        if ($found instanceof Response) {
            return $found;
        }

        Delivery::processDue(5);

        $tab = $this->request->trimmed('vista', 'attivita');
        if (!in_array($tab, ['attivita', 'follower'], true)) {
            $tab = 'attivita';
        }

        return $this->panel('dashboard/notifications', [
            'pageTitle'     => __('federation.notifications.title'),
            'section'       => 'notifications',
            'blog'          => $found,
            'tab'           => $tab,
            'handle'        => ActivityPub::handle($found),
            'enabled'       => ActivityPub::blogEnabled($found),
            'instanceOn'    => ActivityPub::instanceEnabled(),
            'followers'     => ApFollower::allFor($found),
            'followerCount' => ApFollower::count($found),
            'notifications' => ApNotification::recent($found),
            'unread'        => ApNotification::unreadCount($found->id),
        ]);
    }

    public function markRead(string $blog): Response
    {
        $found = $this->openBlog($blog);
        if ($found instanceof Response) {
            return $found;
        }
        $csrf = $this->requireCsrf();
        if ($csrf !== null) {
            return $csrf;
        }

        $id = $this->request->int('id');
        if ($id > 0) {
            $item = ApNotification::find($id);
            if ($item !== null && $item->blog_id === $found->id) {
                $item->markRead();
            }
        } else {
            ApNotification::markAllRead($found);
        }

        return $this->succeed(
            $this->blogUrl($found, '/notifiche'),
            __('federation.notifications.marked_read')
        );
    }
}
