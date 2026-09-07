<?php

declare(strict_types=1);

use Noblogs\Core\I18n;

if (!function_exists('e')) {
    /** Escape per il contesto HTML. */
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('__')) {
    /**
     * Traduce una chiave nella lingua attiva.
     *
     * @param array<string,string|int> $replacements
     */
    function __(string $key, array $replacements = []): string
    {
        return I18n::translate($key, $replacements);
    }
}

if (!function_exists('attr')) {
    /**
     * Rende una lista di attributi HTML, saltando i valori null e false.
     *
     * @param array<string,string|int|bool|null> $attributes
     */
    function attr(array $attributes): string
    {
        $parts = [];
        foreach ($attributes as $name => $value) {
            if ($value === null || $value === false) {
                continue;
            }
            if ($value === true) {
                $parts[] = e($name);
                continue;
            }
            $parts[] = e($name) . '="' . e((string) $value) . '"';
        }
        return implode(' ', $parts);
    }
}
