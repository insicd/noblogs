<?php

declare(strict_types=1);

namespace Noblogs\Models;

use Noblogs\Core\Database;
use Noblogs\Support\Str;

/**
 * Iscritti agli aggiornamenti di un blog, con conferma via email.
 *
 * La doppia conferma non è un vezzo: senza, chiunque può iscrivere l'indirizzo
 * di qualcun altro, e la lista diventa uno strumento di molestia.
 */
final class Subscriber extends Model
{
    protected static string $table = 'subscribers';

    protected static array $casts = [
        'id'        => 'int',
        'blog_id'   => 'int',
        'confirmed' => 'bool',
    ];

    public int $blog_id = 0;
    public string $email = '';
    public bool $confirmed = false;
    public string $token = '';
    public string $created_at = '';
    public ?string $confirmed_at = null;

    /**
     * Aggiunge un indirizzo in attesa di conferma.
     *
     * Se l'indirizzo esiste già e non è confermato ne rigenera il token, così
     * un secondo tentativo rimanda l'email senza svelare che era già presente.
     */
    public static function subscribe(Blog $blog, string $email): ?self
    {
        $email = mb_strtolower(trim($email));
        if (!Str::isEmail($email)) {
            return null;
        }

        $existing = self::hydrateOrNull(Database::instance()->fetch(
            'SELECT * FROM {{subscribers}} WHERE blog_id = ? AND email = ?',
            [$blog->id, $email]
        ));

        if ($existing !== null) {
            if ($existing->confirmed) {
                return null;
            }
            $existing->token = Str::token(24);
            $existing->updateRow(['token' => $existing->token]);
            return $existing;
        }

        $subscriber = new self();
        $subscriber->blog_id = $blog->id;
        $subscriber->email = $email;
        $subscriber->token = Str::token(24);
        $subscriber->created_at = self::now();
        $subscriber->insertRow([
            'blog_id'    => $subscriber->blog_id,
            'email'      => $subscriber->email,
            'confirmed'  => 0,
            'token'      => $subscriber->token,
            'created_at' => $subscriber->created_at,
        ]);

        return $subscriber;
    }

    public static function findByToken(string $token): ?self
    {
        if ($token === '') {
            return null;
        }
        return self::hydrateOrNull(Database::instance()->fetch(
            'SELECT * FROM {{subscribers}} WHERE token = ?',
            [$token]
        ));
    }

    public function confirm(): void
    {
        if ($this->confirmed) {
            return;
        }
        $this->confirmed = true;
        $this->confirmed_at = self::now();
        // Il token resta valido: è quello che consente la cancellazione dai
        // link in fondo alle email.
        $this->updateRow([
            'confirmed'    => 1,
            'confirmed_at' => $this->confirmed_at,
        ]);
    }

    /** @return list<self> */
    public static function forBlog(Blog $blog, bool $confirmedOnly = true): array
    {
        $sql = 'SELECT * FROM {{subscribers}} WHERE blog_id = ?';
        if ($confirmedOnly) {
            $sql .= ' AND confirmed = 1';
        }
        $sql .= ' ORDER BY created_at DESC';

        return self::hydrateAll(Database::instance()->fetchAll($sql, [$blog->id]));
    }

    public static function countForBlog(Blog $blog, bool $confirmedOnly = true): int
    {
        $sql = 'SELECT COUNT(*) FROM {{subscribers}} WHERE blog_id = ?';
        if ($confirmedOnly) {
            $sql .= ' AND confirmed = 1';
        }
        return (int) Database::instance()->fetchColumn($sql, [$blog->id]);
    }

    /** Rimuove le iscrizioni mai confermate dopo una settimana. */
    public static function pruneUnconfirmed(): int
    {
        return Database::instance()->query(
            'DELETE FROM {{subscribers}}
             WHERE confirmed = 0 AND created_at < UTC_TIMESTAMP() - INTERVAL 7 DAY'
        )->rowCount();
    }

    public static function exportCsv(Blog $blog): string
    {
        $lines = ["email,data_iscrizione"];
        foreach (self::forBlog($blog) as $subscriber) {
            $lines[] = '"' . str_replace('"', '""', $subscriber->email) . '",' . $subscriber->confirmed_at;
        }
        return implode("\n", $lines) . "\n";
    }
}
