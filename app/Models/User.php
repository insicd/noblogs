<?php

declare(strict_types=1);

namespace Noblogs\Models;

use Noblogs\Core\Auth;
use Noblogs\Core\Config;
use Noblogs\Core\I18n;
use Noblogs\Support\Dates;
use Noblogs\Support\Str;

final class User extends Model
{
    protected static string $table = 'users';

    protected static array $casts = [
        'id'        => 'int',
        'is_active' => 'bool',
        'max_blogs' => 'int',
    ];

    public string $email = '';
    public string $password_hash = '';
    public string $role = 'user';
    public bool $is_active = true;
    public int $max_blogs = 3;
    public string $locale = 'it';
    public string $timezone = 'Europe/Rome';
    public ?string $email_verified_at = null;
    public ?string $verify_token = null;
    public ?string $reset_token = null;
    public ?string $reset_expires_at = null;
    public ?string $dashboard_css = null;
    public string $created_at = '';
    public ?string $last_login_at = null;

    public static function findByEmail(string $email): ?self
    {
        return self::hydrateOrNull(self::db()->fetch(
            'SELECT * FROM {{users}} WHERE email = ?',
            [mb_strtolower(trim($email))]
        ));
    }

    public static function findByVerifyToken(string $token): ?self
    {
        if ($token === '') {
            return null;
        }
        return self::hydrateOrNull(self::db()->fetch(
            'SELECT * FROM {{users}} WHERE verify_token = ?',
            [$token]
        ));
    }

    public static function findByResetToken(string $token): ?self
    {
        if ($token === '') {
            return null;
        }
        return self::hydrateOrNull(self::db()->fetch(
            'SELECT * FROM {{users}} WHERE reset_token = ? AND reset_expires_at > UTC_TIMESTAMP()',
            [$token]
        ));
    }

    public static function emailTaken(string $email): bool
    {
        return self::db()->fetchColumn(
            'SELECT 1 FROM {{users}} WHERE email = ?',
            [mb_strtolower(trim($email))]
        ) !== null;
    }

    public static function create(string $email, string $password, string $role = 'user'): self
    {
        $user = new self();
        $user->email = mb_strtolower(trim($email));
        $user->password_hash = Auth::hash($password);
        $user->role = $role;
        $user->locale = I18n::isUiLocale(I18n::locale())
            ? I18n::locale()
            : (string) Config::get('site.locale', 'it');
        $user->timezone = (string) Config::get('site.timezone', 'Europe/Rome');
        $user->max_blogs = (int) Config::get('limits.blogs_per_user', 3);
        $user->created_at = self::now();
        $user->verify_token = Config::get('security.require_email_verification', true)
            ? Str::token(24)
            : null;
        if ($user->verify_token === null) {
            $user->email_verified_at = $user->created_at;
        }

        $user->insertRow([
            'email'             => $user->email,
            'password_hash'     => $user->password_hash,
            'role'              => $user->role,
            'is_active'         => 1,
            'max_blogs'         => $user->max_blogs,
            'locale'            => $user->locale,
            'timezone'          => $user->timezone,
            'email_verified_at' => $user->email_verified_at,
            'verify_token'      => $user->verify_token,
            'created_at'        => $user->created_at,
        ]);

        return $user;
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isModerator(): bool
    {
        return $this->role === 'admin' || $this->role === 'moderator';
    }

    public function hasVerifiedEmail(): bool
    {
        return $this->email_verified_at !== null
            || !Config::get('security.require_email_verification', true);
    }

    public function markEmailVerified(): void
    {
        $this->email_verified_at = self::now();
        $this->verify_token = null;
        $this->updateRow([
            'email_verified_at' => $this->email_verified_at,
            'verify_token'      => null,
        ]);
    }

    public function setPassword(string $password): void
    {
        $this->password_hash = Auth::hash($password);
        $this->reset_token = null;
        $this->reset_expires_at = null;
        $this->updateRow([
            'password_hash'    => $this->password_hash,
            'reset_token'      => null,
            'reset_expires_at' => null,
        ]);
    }

    public function startPasswordReset(): string
    {
        $this->reset_token = Str::token(24);
        $this->reset_expires_at = Dates::now()->modify('+2 hours')->format('Y-m-d H:i:s');
        $this->updateRow([
            'reset_token'      => $this->reset_token,
            'reset_expires_at' => $this->reset_expires_at,
        ]);
        return $this->reset_token;
    }

    public function newVerifyToken(): string
    {
        $this->verify_token = Str::token(24);
        $this->updateRow(['verify_token' => $this->verify_token]);
        return $this->verify_token;
    }

    /** @param array<string,mixed> $data */
    public function update(array $data): void
    {
        $allowed = ['email', 'role', 'is_active', 'max_blogs', 'locale', 'timezone', 'dashboard_css'];
        $payload = array_intersect_key($data, array_flip($allowed));
        if ($payload === []) {
            return;
        }
        foreach ($payload as $key => $value) {
            if (property_exists($this, $key)) {
                $this->$key = is_bool($this->$key) ? (bool) $value : $value;
            }
        }
        $this->updateRow($payload);
    }

    public function touchLogin(): void
    {
        $this->last_login_at = self::now();
        self::db()->query('UPDATE {{users}} SET last_login_at = ? WHERE id = ?', [$this->last_login_at, $this->id]);
    }

    /** @return list<Blog> */
    public function blogs(): array
    {
        return Blog::forUser($this->id);
    }

    public function blogCount(): int
    {
        return (int) self::db()->fetchColumn('SELECT COUNT(*) FROM {{blogs}} WHERE user_id = ?', [$this->id]);
    }

    public function canCreateBlog(): bool
    {
        return $this->is_active
            && $this->hasVerifiedEmail()
            && $this->blogCount() < $this->max_blogs;
    }
}
