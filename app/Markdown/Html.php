<?php

declare(strict_types=1);

namespace Noblogs\Markdown;

/**
 * Escaping e bonifica degli URL per l'output del parser.
 */
final class Html
{
    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Restituisce l'URL se è sicuro da emettere, altrimenti null.
     *
     * Il controllo dello schema avviene su una copia "normalizzata": le entità
     * HTML vengono decodificate ripetutamente e spazi, tabulazioni e caratteri
     * di controllo rimossi, perché i browser fanno lo stesso e `java&#09;script:`
     * o `&#106;avascript:` sarebbero altrimenti eseguibili. L'URL restituito
     * resta però quello originale: normalizzare l'output romperebbe URL legittimi.
     */
    public static function safeUrl(string $url, bool $forImage = false): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return '';
        }

        $probe = $url;
        for ($i = 0; $i < 5; $i++) {
            $decoded = html_entity_decode($probe, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            if ($decoded === $probe) {
                break;
            }
            $probe = $decoded;
        }

        $probe = (string) preg_replace('/[\x00-\x20\x7F]+/', '', $probe);
        $probe = preg_replace('/[\p{Z}\p{C}]+/u', '', $probe) ?? $probe;
        $probe = strtolower($probe);

        if (preg_match('/^(?:javascript|vbscript|livescript|mocha|about|blob|filesystem):/', $probe) === 1) {
            return null;
        }

        if (str_starts_with($probe, 'data:')) {
            // I data URI sono ammessi solo come sorgente di un'immagine: come
            // href navigherebbero verso contenuto arbitrario controllato dall'autore.
            return $forImage && str_starts_with($probe, 'data:image/') ? $url : null;
        }

        return $url;
    }
}
