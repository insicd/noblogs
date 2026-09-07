<?php

declare(strict_types=1);

/**
 * Stringhe condivise da tutta l'applicazione.
 *
 * Le chiavi sono prefissate per area, così i cataloghi divisi per file si
 * possono unire senza collisioni.
 */
return [
    // Errori generali
    'error.not_found'          => 'Pagina non trovata.',
    'error.method_not_allowed' => 'Metodo non consentito.',
    'error.csrf'               => 'La sessione è scaduta. Riprova a inviare il modulo.',
    'error.forbidden'          => 'Non hai accesso a questa pagina.',
    'error.server'             => 'Il server ha incontrato un errore imprevisto.',
    'error.rate_limited'       => 'Troppi tentativi. Aspetta qualche minuto e riprova.',

    // Moduli
    'form.leave_empty'   => 'Lascia vuoto questo campo',
    'form.save'          => 'Salva',
    'form.cancel'        => 'Annulla',
    'form.delete'        => 'Elimina',
    'form.confirm'       => 'Conferma',
    'form.required'      => 'obbligatorio',
    'form.optional'      => 'facoltativo',

    // Validazione dei sottodomini
    'blog.error.subdomain_required' => 'Scegli un indirizzo per il blog.',
    'blog.error.subdomain_length'   => 'L\'indirizzo deve essere lungo tra 3 e 63 caratteri.',
    'blog.error.subdomain_format'   => 'L\'indirizzo può contenere solo lettere minuscole, numeri e trattini singoli, e non può iniziare o finire con un trattino.',
    'blog.error.subdomain_reserved' => 'Questo indirizzo è riservato al sistema. Scegline un altro.',
    'blog.error.subdomain_taken'    => 'Questo indirizzo è già occupato.',

    // Intestazione dei file markdown
    'frontmatter.warning.unknown_key' => 'Chiave non riconosciuta nell\'intestazione: :key',
    'frontmatter.warning.malformed'   => 'Riga dell\'intestazione senza due punti: :line',

    // File caricati
    'media.error.too_large'        => 'Il file supera il limite di :size.',
    'media.error.too_large_php'    => 'Il file supera il limite di caricamento del server.',
    'media.error.too_many_files'   => 'Hai raggiunto il numero massimo di file per questo blog.',
    'media.error.quota'            => 'Hai esaurito lo spazio disponibile (:size).',
    'media.error.type_not_allowed' => 'I file con estensione .:ext non sono ammessi.',
    'media.error.type_mismatch'    => 'Il contenuto del file non corrisponde alla sua estensione.',
    'media.error.directory'        => 'Non è stato possibile creare la cartella di destinazione.',
    'media.error.write'            => 'Non è stato possibile salvare il file sul server.',
    'media.error.partial'          => 'Il caricamento si è interrotto prima di completarsi.',
    'media.error.no_file'          => 'Nessun file selezionato.',
    'media.error.generic'          => 'Caricamento non riuscito.',

    // Statistiche
    'analytics.homepage' => 'Homepage',
];
