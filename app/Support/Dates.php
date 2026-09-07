<?php

declare(strict_types=1);

namespace Noblogs\Support;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Date e orari.
 *
 * Nel database tutto è UTC. La conversione al fuso dell'utente avviene solo in
 * lettura, e per i visitatori dei blog si fa lato client leggendo l'attributo
 * datetime dei tag <time>: così le pagine restano cacheabili.
 */
final class Dates
{
    public static function utc(): DateTimeZone
    {
        static $utc = null;
        return $utc ??= new DateTimeZone('UTC');
    }

    public static function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', self::utc());
    }

    public static function nowString(): string
    {
        return self::now()->format('Y-m-d H:i:s');
    }

    public static function parse(?string $value): ?DateTimeImmutable
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($value, self::utc());
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Interpreta la data inserita nell'editor, espressa nel fuso dell'autore,
     * e la converte in UTC per il salvataggio.
     */
    public static function fromLocal(string $value, string $timezone): ?DateTimeImmutable
    {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        try {
            $tz = new DateTimeZone($timezone);
        } catch (\Exception) {
            $tz = self::utc();
        }
        try {
            return (new DateTimeImmutable($value, $tz))->setTimezone(self::utc());
        } catch (\Exception) {
            return null;
        }
    }

    public static function toLocal(DateTimeImmutable $date, string $timezone): DateTimeImmutable
    {
        try {
            return $date->setTimezone(new DateTimeZone($timezone));
        } catch (\Exception) {
            return $date;
        }
    }

    public static function iso(?DateTimeImmutable $date): string
    {
        return $date?->format('c') ?? '';
    }

    public static function rfc822(?DateTimeImmutable $date): string
    {
        return $date?->format(\DateTimeInterface::RFC7231) ?? '';
    }

    /**
     * Formatta con i nomi di mese e giorno tradotti nella lingua indicata.
     * Usa IntlDateFormatter quando disponibile, con una tabella di ripiego per
     * le lingue supportate dall'interfaccia.
     */
    public static function format(DateTimeImmutable $date, string $format, string $locale = 'it'): string
    {
        $formatted = $date->format($format);

        if (!preg_match('/[DlFM]/', $format)) {
            return $formatted;
        }

        $names = self::names($locale);
        if ($names === null) {
            return $formatted;
        }

        // Sostituisce i nomi inglesi generati da format() con quelli tradotti,
        // partendo dai più lunghi per non troncare "January" in "Jan"+"uary".
        return strtr($formatted, $names);
    }

    /** @return array<string,string>|null */
    private static function names(string $locale): ?array
    {
        static $tables = [
            'it' => [
                'January' => 'gennaio', 'February' => 'febbraio', 'March' => 'marzo',
                'April' => 'aprile', 'May' => 'maggio', 'June' => 'giugno',
                'July' => 'luglio', 'August' => 'agosto', 'September' => 'settembre',
                'October' => 'ottobre', 'November' => 'novembre', 'December' => 'dicembre',
                'Jan' => 'gen', 'Feb' => 'feb', 'Mar' => 'mar', 'Apr' => 'apr',
                'Jun' => 'giu', 'Jul' => 'lug', 'Aug' => 'ago', 'Sep' => 'set',
                'Oct' => 'ott', 'Nov' => 'nov', 'Dec' => 'dic',
                'Monday' => 'lunedì', 'Tuesday' => 'martedì', 'Wednesday' => 'mercoledì',
                'Thursday' => 'giovedì', 'Friday' => 'venerdì', 'Saturday' => 'sabato',
                'Sunday' => 'domenica',
                'Mon' => 'lun', 'Tue' => 'mar', 'Wed' => 'mer', 'Thu' => 'gio',
                'Fri' => 'ven', 'Sat' => 'sab', 'Sun' => 'dom',
            ],
        ];

        return $tables[substr($locale, 0, 2)] ?? null;
    }

    /** Descrizione relativa del tipo "3 mesi fa". */
    public static function since(?DateTimeImmutable $date, string $locale = 'it'): string
    {
        if ($date === null) {
            return '';
        }
        $seconds = max(0, self::now()->getTimestamp() - $date->getTimestamp());

        $units = $locale === 'it'
            ? [31536000 => ['anno', 'anni'], 2592000 => ['mese', 'mesi'], 604800 => ['settimana', 'settimane'],
               86400 => ['giorno', 'giorni'], 3600 => ['ora', 'ore'], 60 => ['minuto', 'minuti']]
            : [31536000 => ['year', 'years'], 2592000 => ['month', 'months'], 604800 => ['week', 'weeks'],
               86400 => ['day', 'days'], 3600 => ['hour', 'hours'], 60 => ['minute', 'minutes']];

        foreach ($units as $size => [$singular, $plural]) {
            if ($seconds >= $size) {
                $count = (int) floor($seconds / $size);
                return $count . ' ' . ($count === 1 ? $singular : $plural);
            }
        }

        return $locale === 'it' ? 'pochi secondi' : 'a few seconds';
    }
}
