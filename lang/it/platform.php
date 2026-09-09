<?php

declare(strict_types=1);

/**
 * Stringhe delle pagine pubbliche della piattaforma: pagina di ingresso,
 * vetrina, pagine informative e pagine di errore.
 *
 * Il testo lungo delle pagine informativa, termini, aiuto e guida al markdown
 * sta direttamente nelle viste: è prosa, non etichette, e tenerla qui la
 * renderebbe soltanto più difficile da rileggere.
 */
return [
    // -----------------------------------------------------------------------
    // Guscio comune
    // -----------------------------------------------------------------------
    'platform.nav.label'      => 'Navigazione della piattaforma',
    'platform.nav.discover'   => 'Esplora',
    'platform.nav.help'       => 'Aiuto',
    'platform.nav.about'      => 'Informazioni',
    'platform.nav.login'      => 'Accedi',
    'platform.nav.register'   => 'Registrati',
    'platform.nav.dashboard'  => 'Dashboard',
    'platform.skip'           => 'Salta al contenuto',
    'platform.flash.label'    => 'Messaggi',

    'platform.footer.privacy'     => 'Privacy',
    'platform.footer.terms'       => 'Termini',
    'platform.footer.help'        => 'Aiuto',
    'platform.footer.source'      => 'Codice sorgente',
    'platform.footer.no_tracking' => 'Nessun tracciamento, nessun cookie di profilazione, nessun servizio di terze parti.',
    'platform.lang.label'         => 'Lingua',

    // -----------------------------------------------------------------------
    // Pagina di ingresso
    // -----------------------------------------------------------------------
    'platform.home.title'          => 'Blog leggeri, senza tracciamento',
    'platform.home.heading'        => 'Scrivi. Semplicemente.',
    'platform.home.lead'           => 'Noblogs è un servizio gratuito di blog: nessuna pubblicità, nessun tracciamento, nessun piano a pagamento. Scrivi in Markdown, la pagina che ne esce pesa pochi kilobyte e si legge su qualunque dispositivo.',
    'platform.home.form_heading'   => 'Apri il tuo blog',
    'platform.home.form_note'      => 'Ti servono un indirizzo email e trenta secondi.',
    'platform.home.form_submit'    => 'Comincia',
    'platform.home.dashboard_heading' => 'Sei già dentro',
    'platform.home.dashboard_link'    => 'Vai alla tua dashboard',
    'platform.home.stats_blogs'    => 'blog attivi',
    'platform.home.stats_posts'    => 'articoli pubblicati',
    'platform.home.recent_heading' => 'Dalla vetrina',
    'platform.home.recent_more'    => 'Esplora tutti gli articoli',
    'platform.home.features_heading' => 'Come funziona',

    'platform.home.feature.privacy.title' => 'Statistiche senza spiare',
    'platform.home.feature.privacy.body'  => 'Le letture si contano con un\'impronta giornaliera non reversibile: nessun cookie, nessun indirizzo IP conservato, nessun profilo. I lettori dei blog non ricevono nemmeno un cookie.',
    'platform.home.feature.speed.title'   => 'Pagine leggere',
    'platform.home.feature.speed.body'    => 'HTML e un foglio di stile. Nessun framework, nessun font esterno, nessuno script di terze parti: le pagine si aprono anche con una connessione lenta.',
    'platform.home.feature.markdown.title' => 'Markdown, non moduli',
    'platform.home.feature.markdown.body'  => 'Scrivi in Markdown, con tabelle, note a piè di pagina, blocchi di codice e direttive per inserire elenchi di articoli dove ti servono.',
    'platform.home.feature.yours.title'   => 'I contenuti restano tuoi',
    'platform.home.feature.yours.body'    => 'Puoi esportare tutto in file Markdown quando vuoi, collegare un dominio tuo e cancellare l\'account con i suoi contenuti in qualsiasi momento.',

    // -----------------------------------------------------------------------
    // Vetrina
    // -----------------------------------------------------------------------
    'discover.title'          => 'Esplora',
    'discover.heading'        => 'Esplora',
    'discover.intro'          => 'Articoli dai blog ospitati qui, scelti da chi li scrive. Compaiono solo i blog approvati dalla moderazione.',
    'discover.filters_label'  => 'Filtri della vetrina',
    'discover.order_label'    => 'Ordina',
    'discover.lang_label'     => 'Lingua',
    'discover.order.score'    => 'In evidenza',
    'discover.order.recent'   => 'Più recenti',
    'discover.order.random'   => 'A caso',
    'discover.lang_label'     => 'Lingua',
    'discover.lang_all'       => 'Tutte',
    'discover.filter_submit'  => 'Applica',
    'discover.empty'          => 'Non c\'è ancora nulla da mostrare qui.',
    'discover.feed'           => 'Feed della vetrina',
    'discover.random'         => 'Portami su un articolo a caso',
    'discover.random_blog'    => 'Portami su un blog a caso',
    'discover.upvotes'        => ':count apprezzamenti',
    'discover.on_blog'        => 'su :blog',
    'discover.pagination'     => 'Paginazione della vetrina',
    'discover.newer'          => 'Pagina precedente',
    'discover.older'          => 'Pagina successiva',
    'discover.page_of'        => 'Pagina :current di :total',

    'discover.search_title'       => 'Cerca nella vetrina',
    'discover.search_label'       => 'Cerca fra articoli e blog',
    'discover.search_placeholder' => 'Titolo, tag o nome del blog',
    'discover.search_button'      => 'Cerca',
    'discover.search_hint'        => 'La ricerca guarda il titolo dell\'articolo, i suoi tag, l\'indirizzo e il titolo del blog. Tutte le parole devono comparire.',
    'discover.search_results'     => 'Risultati per «:query»',
    'discover.search_count'       => ':count risultati',
    'discover.search_empty'       => 'Nessun risultato per «:query».',

    // -----------------------------------------------------------------------
    // Titoli delle pagine informative
    // -----------------------------------------------------------------------
    'platform.about.title'    => 'Informazioni',
    'platform.privacy.title'  => 'Informativa sulla privacy',
    'platform.terms.title'    => 'Condizioni d\'uso',
    'platform.help.title'     => 'Aiuto',
    'platform.markdown.title' => 'Guida al Markdown',

    'platform.markdown.source'     => 'Come si scrive',
    'platform.markdown.result'     => 'Come viene mostrato',
    'platform.markdown.contents'   => 'In questa pagina',
    'platform.markdown.directive_code' => 'Direttiva',
    'platform.markdown.directive_aliases' => 'Sinonimi',
    'platform.markdown.directive_effect' => 'Cosa inserisce',

    // -----------------------------------------------------------------------
    // Pagine di errore con il guscio della piattaforma
    // -----------------------------------------------------------------------
    'errors.404.title'    => 'Pagina non trovata',
    'errors.404.body'     => 'Questo indirizzo non corrisponde a nessuna pagina. Può essere stato scritto male, oppure la pagina non esiste più.',
    'errors.404.links'    => 'Da qui puoi ripartire:',
    'errors.403.title'    => 'Accesso non consentito',
    'errors.403.body'     => 'Non hai i permessi per vedere questa pagina. Se pensi che sia un errore, prova a uscire e ad accedere di nuovo.',
    'errors.home'         => 'Pagina iniziale',
];
