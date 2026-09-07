<?php

declare(strict_types=1);

namespace Noblogs\Controllers\Admin;

use Noblogs\Core\Response;
use Noblogs\Core\Url;
use Noblogs\Models\Setting;
use Noblogs\Support\Str;

/**
 * Impostazioni della piattaforma modificabili senza toccare config.php.
 *
 * Le chiavi coincidono con i percorsi puntati della configurazione (per
 * esempio `site.name`): quello che sta nel database ha la precedenza su quello
 * che sta nel file, così un valore si cambia a caldo e si torna indietro
 * svuotando il campo. Le impostazioni che non si possono cambiare a caldo —
 * database, dominio, modalità di routing, salt — restano solo in config.php,
 * perché sbagliarle da un modulo web significa perdere l'accesso al pannello.
 */
final class SettingsController extends AdminController
{
    /** Un megabyte, per i campi che si esprimono in MB ma si salvano in byte. */
    private const MB = 1048576;

    public function edit(): Response
    {
        if ($denied = $this->guard(true)) {
            return $denied;
        }

        if ($this->request->isPost()) {
            if ($response = $this->requireCsrf()) {
                return $response;
            }
            return $this->save();
        }

        return $this->panel('admin/settings', [
            'activeNav' => 'settings',
            'values'    => $this->current(),
        ]);
    }

    /** @return array<string,mixed> */
    private function current(): array
    {
        return [
            'site_name'         => (string) self::setting('site.name', 'Noblogs'),
            'tagline'           => (string) self::setting('site.tagline', ''),
            'contact_email'     => (string) self::setting('site.contact_email', ''),
            'verify_email'      => self::settingBool('security.require_email_verification', true),
            'review_blogs'      => self::settingBool('security.require_blog_review', true),
            'registration_open' => self::settingBool('platform.registration_open', true),
            'blogs_per_user'    => (int) self::setting('limits.blogs_per_user', 3),
            'posts_per_blog'    => (int) self::setting('limits.posts_per_blog', 5000),
            'storage_per_blog'  => (int) round(((int) self::setting('limits.storage_per_blog', 524288000)) / self::MB),
            'upload_max'        => (int) round(((int) self::setting('limits.upload_max_bytes', 10485760)) / self::MB),
            'notice'            => (string) self::setting('platform.notice', ''),
        ];
    }

    private function save(): Response
    {
        $name = mb_substr($this->request->trimmed('site_name'), 0, 100);
        $tagline = mb_substr($this->request->trimmed('tagline'), 0, 200);
        $contact = mb_substr($this->request->trimmed('contact_email'), 0, 191);
        $notice = mb_substr($this->request->trimmed('notice'), 0, 2000);

        if ($name === '') {
            return $this->reject(__('admin.settings.error.name_required'));
        }
        if ($contact !== '' && !Str::isEmail($contact)) {
            return $this->reject(__('admin.settings.error.contact_email'));
        }

        $blogsPerUser = $this->request->int('blogs_per_user', 3);
        $postsPerBlog = $this->request->int('posts_per_blog', 5000);
        $storagePerBlog = $this->request->int('storage_per_blog', 500);
        $uploadMax = $this->request->int('upload_max', 10);

        if ($blogsPerUser < 0 || $blogsPerUser > 1000
            || $postsPerBlog < 1 || $postsPerBlog > 1000000
            || $storagePerBlog < 1 || $storagePerBlog > 1048576
            || $uploadMax < 1 || $uploadMax > 1024) {
            return $this->reject(__('admin.settings.error.limits'));
        }
        // Un limite per file più alto della quota totale sarebbe una promessa
        // che il primo caricamento smentisce.
        if ($uploadMax > $storagePerBlog) {
            return $this->reject(__('admin.settings.error.upload_over_quota'));
        }

        Setting::put('site.name', $name);
        Setting::put('site.tagline', $tagline);
        Setting::put('site.contact_email', $contact);
        Setting::put('platform.notice', $notice);
        Setting::put('security.require_email_verification', $this->request->boolean('verify_email') ? '1' : '0');
        Setting::put('security.require_blog_review', $this->request->boolean('review_blogs') ? '1' : '0');
        Setting::put('platform.registration_open', $this->request->boolean('registration_open') ? '1' : '0');
        Setting::put('limits.blogs_per_user', (string) $blogsPerUser);
        Setting::put('limits.posts_per_blog', (string) $postsPerBlog);
        Setting::put('limits.storage_per_blog', (string) ($storagePerBlog * self::MB));
        Setting::put('limits.upload_max_bytes', (string) ($uploadMax * self::MB));

        $this->record(null, 'impostazioni', __('admin.settings.log_note'));
        $this->flash('success', __('admin.settings.saved'));

        return $this->redirect(Url::to('/admin/impostazioni'));
    }

    private function reject(string $error): Response
    {
        return $this->withInput(Url::to('/admin/impostazioni'), $this->request->post, $error);
    }
}
