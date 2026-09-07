<?php
/**
 * Noblogs — configurazione.
 *
 * Copia questo file in config/config.php e adatta i valori, oppure lascia che
 * sia l'installer web (/install/) a generarlo per te.
 */

return [
    // -----------------------------------------------------------------------
    // Database
    // -----------------------------------------------------------------------
    'db' => [
        'host'     => 'localhost',
        'port'     => 3306,
        'name'     => 'noblogs',
        'user'     => 'root',
        'password' => '',
        'prefix'   => '',
        // Su alcuni hosting condivisi il socket non è quello di default.
        'socket'   => null,
    ],

    // -----------------------------------------------------------------------
    // Piattaforma
    // -----------------------------------------------------------------------
    'site' => [
        // Dominio principale su cui girano landing page, registrazione e
        // dashboard. I blog vivono sui suoi sottodomini.
        'domain'        => 'hosting-noblogs.tld',
        // Host alternativi che devono essere trattati come dominio principale
        // (utile per www, staging o l'accesso da localhost).
        'extra_hosts'   => ['localhost', '127.0.0.1'],
        'name'          => 'Noblogs',
        'tagline'       => 'Blog leggeri, senza tracciamento.',
        'https'         => true,
        // Indirizzo usato come mittente delle email di sistema.
        'email_from'    => 'noreply@hosting-noblogs.tld',
        'email_name'    => 'Noblogs',
        // Email dell'amministrazione, mostrata nelle pagine di contatto.
        'contact_email' => 'admin@hosting-noblogs.tld',
        'locale'        => 'it',
        'timezone'      => 'Europe/Rome',
    ],

    // -----------------------------------------------------------------------
    // Come si raggiungono i blog.
    //
    //   'subdomain' → miablog.hosting-noblogs.tld   (richiede DNS wildcard
    //                 *.hosting-noblogs.tld e un vhost wildcard)
    //   'path'      → hosting-noblogs.tld/miablog/  (nessun requisito DNS)
    //
    // Con 'path_fallback' attivo i blog restano raggiungibili anche via path
    // pur avendo i sottodomini come URL canonico: comodo per testare prima di
    // configurare il DNS, o come rete di sicurezza.
    // -----------------------------------------------------------------------
    'routing' => [
        'mode'          => 'subdomain',
        'path_fallback' => true,
        // Consente ai blog di rispondere anche su un dominio proprio.
        'custom_domains' => true,
    ],

    // -----------------------------------------------------------------------
    // Sicurezza. Cambia ASSOLUTAMENTE i salt su un'installazione reale:
    // l'installer ne genera di casuali.
    // -----------------------------------------------------------------------
    'security' => [
        // Usato per anonimizzare gli IP nelle statistiche. Cambiarlo azzera la
        // continuità dei conteggi di visitatori unici.
        'analytics_salt' => 'CAMBIAMI-analytics',
        // Firma i token di sessione, gli upvote e i link firmati.
        'app_key'        => 'CAMBIAMI-appkey',
        // Registrazione aperta ma i nuovi blog restano noindex finché un
        // moderatore non li approva.
        'require_email_verification' => true,
        'require_blog_review'        => true,
        // Sottodomini che nessuno può registrare.
        'reserved_subdomains' => [
            'www', 'mail', 'smtp', 'imap', 'pop', 'ftp', 'ns', 'ns1', 'ns2',
            'admin', 'api', 'app', 'blog', 'cdn', 'static', 'assets', 'media',
            'docs', 'doc', 'help', 'support', 'status', 'dashboard', 'panel',
            'login', 'signup', 'register', 'account', 'accounts', 'user',
            'users', 'test', 'dev', 'staging', 'demo', 'beta', 'noblogs',
            'discover', 'search', 'feed', 'rss', 'atom', 'sitemap', 'robots',
            'security', 'abuse', 'postmaster', 'webmaster', 'hostmaster',
            'moderation', 'staff', 'system', 'root', 'null', 'undefined',
        ],
        // Indirizzi dei reverse proxy da cui accettare X-Forwarded-For e
        // CF-Connecting-IP. Lasciare vuoto se PHP vede già l'IP del visitatore.
        'trusted_proxies' => [],
    ],

    // -----------------------------------------------------------------------
    // Quote. Valori generosi: servono a contenere gli abusi, non a spingere
    // verso un piano a pagamento (su Noblogs non esiste).
    // -----------------------------------------------------------------------
    'limits' => [
        'blogs_per_user'      => 3,
        'posts_per_blog'      => 5000,
        'post_max_chars'      => 1000000,
        'upload_max_bytes'    => 10 * 1024 * 1024,
        'storage_per_blog'    => 500 * 1024 * 1024,
        'files_per_blog'      => 2000,
        'image_max_width'     => 1600,
        // Giorni di conservazione delle statistiche (0 = per sempre).
        'analytics_retention' => 365,
    ],

    // -----------------------------------------------------------------------
    // Invio email. 'mail' usa la funzione mail() di PHP (di norma l'unica
    // disponibile su hosting condiviso), 'smtp' si collega a un server SMTP,
    // 'log' scrive i messaggi in storage/logs/mail.log senza inviarli.
    // -----------------------------------------------------------------------
    'mail' => [
        'driver'   => 'mail',
        'smtp' => [
            'host'       => 'localhost',
            'port'       => 587,
            'user'       => '',
            'password'   => '',
            'encryption' => 'tls', // tls, ssl oppure null
        ],
    ],

    // -----------------------------------------------------------------------
    // Sviluppo
    // -----------------------------------------------------------------------
    'debug' => false,
];
