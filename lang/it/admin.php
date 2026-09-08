<?php

declare(strict_types=1);

/**
 * Stringhe dell'area di amministrazione.
 *
 * Tutte le chiavi sono prefissate con `admin.`: i cataloghi divisi per file
 * vengono uniti in un solo array e il prefisso evita le collisioni.
 */
return [
    // Intestazione e navigazione
    'admin.area'                   => 'Amministrazione',
    'admin.area_short'             => 'Admin',
    'admin.nav.label'              => 'Sezioni dell\'amministrazione',
    'admin.nav.dashboard'          => 'Quadro generale',
    'admin.nav.blogs'              => 'Blog',
    'admin.nav.users'              => 'Utenti',
    'admin.nav.log'                => 'Registro',
    'admin.nav.settings'           => 'Impostazioni',
    'admin.nav.back_to_dashboard'  => 'La mia dashboard',
    'admin.nav.logout'             => 'Esci',
    'admin.footer'                 => 'Noblogs :version — area riservata a moderatori e amministratori.',

    'admin.role.admin'     => 'Amministratore',
    'admin.role.moderator' => 'Moderatore',
    'admin.role.user'      => 'Utente',

    'admin.notice.title' => 'Avviso della piattaforma',

    'admin.note.label'       => 'Nota del moderatore',
    'admin.note.placeholder' => 'Nota (facoltativa)',

    'admin.filters.apply' => 'Applica',

    'admin.pagination.label'    => 'Paginazione',
    'admin.pagination.previous' => 'Precedente',
    'admin.pagination.next'     => 'Successiva',
    'admin.pagination.position' => 'Pagina :current di :total',

    'admin.error.unknown_action' => 'Azione non riconosciuta.',

    // ---------------------------------------------------------------------
    // Quadro generale
    // ---------------------------------------------------------------------
    'admin.dashboard.title' => 'Quadro generale',

    'admin.dashboard.stat.blogs'   => 'Blog',
    'admin.dashboard.stat.pending' => 'In attesa di revisione',
    'admin.dashboard.stat.users'   => 'Utenti',
    'admin.dashboard.stat.posts'   => 'Articoli',
    'admin.dashboard.stat.reads'   => 'Letture (30 giorni)',
    'admin.dashboard.stat.storage' => 'Spazio occupato',
    'admin.dashboard.secondary'    => 'Nascosti: :hidden · Segnalati: :flagged · Moderatori e amministratori: :staff',

    'admin.dashboard.queue.title'      => 'Coda di moderazione',
    'admin.dashboard.queue.intro'      => 'I blog che aspettano una decisione, dal più sospetto in giù. L\'estratto serve a decidere senza doverli aprire uno per uno.',
    'admin.dashboard.queue.empty'      => 'Nessun blog in attesa: la coda è vuota.',
    'admin.dashboard.queue.owner'      => 'di :email',
    'admin.dashboard.queue.created'    => 'creato :since fa',
    'admin.dashboard.queue.posts'      => ':count articoli',
    'admin.dashboard.queue.posts_one'  => '1 articolo',
    'admin.dashboard.queue.score'      => 'rischio :score',
    'admin.dashboard.queue.no_content' => 'La homepage è vuota.',

    'admin.dashboard.recent_log' => 'Ultime decisioni',
    'admin.dashboard.full_log'   => 'Vedi il registro completo',

    // ---------------------------------------------------------------------
    // Manutenzione
    // ---------------------------------------------------------------------
    'admin.maintenance.title'     => 'Manutenzione',
    'admin.maintenance.intro'     => 'Elimina le statistiche oltre il periodo di conservazione, le iscrizioni mai confermate e la cache scaduta; riallinea i temi e ricalcola i contatori dei blog.',
    'admin.maintenance.cron_hint' => 'Le stesse operazioni si possono programmare con «bin/noblogs manutenzione»: se hai accesso al cron, è la strada migliore.',
    'admin.maintenance.run'       => 'Esegui la manutenzione',
    'admin.maintenance.done'      => 'Manutenzione completata: :hits letture eliminate, :subscribers iscrizioni scadute, :cache voci di cache, :themes temi allineati, :blogs blog ricalcolati.',

    // ---------------------------------------------------------------------
    // Blog
    // ---------------------------------------------------------------------
    'admin.blogs.title'              => 'Blog',
    'admin.blogs.search_label'       => 'Cerca',
    'admin.blogs.search_placeholder' => 'Indirizzo, titolo, dominio o email dell\'autore',
    'admin.blogs.filter_label'       => 'Stato',
    'admin.blogs.sort_label'         => 'Ordina per',
    'admin.blogs.count'              => ':count blog trovati.',
    'admin.blogs.count_one'          => 'Un solo blog trovato.',
    'admin.blogs.empty'              => 'Nessun blog corrisponde ai filtri.',

    'admin.blogs.filter.all'       => 'Tutti',
    'admin.blogs.filter.pending'   => 'In attesa',
    'admin.blogs.filter.approved'  => 'Approvati',
    'admin.blogs.filter.hidden'    => 'Nascosti',
    'admin.blogs.filter.flagged'   => 'Segnalati',

    'admin.blogs.sort.recent'   => 'Più recenti',
    'admin.blogs.sort.activity' => 'Ultima pubblicazione',
    'admin.blogs.sort.risk'     => 'Punteggio di rischio',
    'admin.blogs.sort.storage'  => 'Spazio occupato',
    'admin.blogs.sort.alpha'    => 'Indirizzo',

    'admin.blogs.col.blog'    => 'Blog',
    'admin.blogs.col.owner'   => 'Autore',
    'admin.blogs.col.state'   => 'Stato',
    'admin.blogs.col.posts'   => 'Articoli',
    'admin.blogs.col.storage' => 'Spazio',
    'admin.blogs.col.created' => 'Creato',

    'admin.blogs.state.pending'  => 'In attesa',
    'admin.blogs.state.approved' => 'Approvato',
    'admin.blogs.state.hidden'   => 'Nascosto',
    'admin.blogs.state.flagged'  => 'Segnalato',
    'admin.blogs.state.raw_html' => 'HTML libero',

    'admin.blogs.action.approva'              => 'Approva',
    'admin.blogs.action.nascondi'             => 'Nascondi',
    'admin.blogs.action.mostra'               => 'Mostra',
    'admin.blogs.action.segnala'              => 'Segnala',
    'admin.blogs.action.rimuovi_segnalazione' => 'Togli la segnalazione',
    'admin.blogs.action.consenti_html'        => 'Consenti HTML',
    'admin.blogs.action.revoca_html'          => 'Revoca HTML',
    'admin.blogs.action.elimina'              => 'Elimina',

    'admin.blogs.done.approva'              => 'Blog :blog approvato.',
    'admin.blogs.done.nascondi'             => 'Blog :blog nascosto: non è più raggiungibile dal pubblico.',
    'admin.blogs.done.mostra'               => 'Blog :blog di nuovo visibile.',
    'admin.blogs.done.segnala'              => 'Blog :blog segnalato e rimesso in coda di revisione.',
    'admin.blogs.done.rimuovi_segnalazione' => 'Segnalazione rimossa dal blog :blog.',
    'admin.blogs.done.consenti_html'        => 'Il blog :blog può ora usare HTML libero nei contenuti.',
    'admin.blogs.done.revoca_html'          => 'HTML libero revocato al blog :blog.',
    'admin.blogs.done.generic'              => 'Blog :blog aggiornato.',

    'admin.blogs.error.not_found' => 'Questo blog non esiste più.',

    'admin.review.email_subject' => 'Nuovo blog in attesa di approvazione: :title',
    'admin.review.email_body'    => "È stato creato un nuovo blog su :site, in attesa di approvazione.\n\nTitolo: :title\nAccount: :email\nRaggiungibile ora: :path\nDopo l'approvazione: :subdomain\n\nApri il blog:\n:blog_url\n\nCoda di revisione:\n:admin_url\n",
    'admin.review.approved_email_subject' => 'Blog approvato: :title',
    'admin.review.approved_email_body'    => "Hai approvato il blog «:title» su :site. Il dominio di terzo livello è attivo.\n\nTerzo livello: :subdomain\nPercorso (resta valido): :path\nAccount: :email\n",

    'admin.blogs.delete.title'          => 'Elimina :blog',
    'admin.blogs.delete.heading'        => 'Stai per eliminare «:blog»',
    'admin.blogs.delete.warning'        => 'L\'operazione è definitiva e non si può annullare. Spariscono i contenuti, i file caricati, le statistiche e gli iscritti.',
    'admin.blogs.delete.item.address'   => 'Indirizzo',
    'admin.blogs.delete.item.owner'     => 'Autore',
    'admin.blogs.delete.item.posts'     => 'Articoli',
    'admin.blogs.delete.item.pages'     => 'Pagine',
    'admin.blogs.delete.item.files'     => 'File caricati',
    'admin.blogs.delete.item.storage'   => 'Spazio occupato',
    'admin.blogs.delete.item.subscribers' => 'Iscritti',
    'admin.blogs.delete.item.reads'     => 'Letture registrate',
    'admin.blogs.delete.files_note'     => 'Verrà cancellata anche la cartella :path con tutto il suo contenuto.',
    'admin.blogs.delete.alternative'    => 'Se il problema è temporaneo, «Nascondi» toglie il blog dal pubblico lasciando intatto il contenuto: è quasi sempre la scelta giusta.',
    'admin.blogs.delete.type_subdomain' => 'Per confermare, scrivi qui l\'indirizzo del blog: :blog',
    'admin.blogs.delete.confirm_button' => 'Elimina definitivamente',
    'admin.blogs.delete.mismatch'       => 'L\'indirizzo digitato non corrisponde: il blog non è stato eliminato.',
    'admin.blogs.delete.done'           => 'Blog :blog eliminato con tutti i suoi contenuti e i suoi file.',

    // ---------------------------------------------------------------------
    // Utenti
    // ---------------------------------------------------------------------
    'admin.users.title'              => 'Utenti',
    'admin.users.search_label'       => 'Cerca per email',
    'admin.users.search_placeholder' => 'parte dell\'indirizzo email',
    'admin.users.filter_label'       => 'Stato',
    'admin.users.sort_label'         => 'Ordina per',
    'admin.users.count'              => ':count utenti trovati.',
    'admin.users.count_one'          => 'Un solo utente trovato.',
    'admin.users.empty'              => 'Nessun utente corrisponde ai filtri.',
    'admin.users.you'                => 'sei tu',
    'admin.users.never'              => 'mai',
    'admin.users.moderator_scope'    => 'Da moderatore puoi sospendere, riattivare e verificare gli account. Ruoli ed eliminazioni sono riservati agli amministratori.',

    'admin.users.filter.all'        => 'Tutti',
    'admin.users.filter.active'     => 'Attivi',
    'admin.users.filter.suspended'  => 'Sospesi',
    'admin.users.filter.unverified' => 'Email non verificata',
    'admin.users.filter.staff'      => 'Moderatori e amministratori',

    'admin.users.sort.recent' => 'Più recenti',
    'admin.users.sort.login'  => 'Ultimo accesso',
    'admin.users.sort.email'  => 'Email',
    'admin.users.sort.blogs'  => 'Numero di blog',

    'admin.users.col.email'      => 'Email',
    'admin.users.col.role'       => 'Ruolo',
    'admin.users.col.blogs'      => 'Blog / limite',
    'admin.users.col.state'      => 'Stato',
    'admin.users.col.registered' => 'Registrato',
    'admin.users.col.last_login' => 'Ultimo accesso',

    'admin.users.state.active'     => 'Attivo',
    'admin.users.state.suspended'  => 'Sospeso',
    'admin.users.state.unverified' => 'Email non verificata',

    'admin.users.limit_label' => 'Limite di blog',

    'admin.users.action.sospendi'            => 'Sospendi',
    'admin.users.action.riattiva'            => 'Riattiva',
    'admin.users.action.verifica_email'      => 'Segna email come verificata',
    'admin.users.action.promuovi_moderatore' => 'Rendi moderatore',
    'admin.users.action.promuovi_admin'      => 'Rendi amministratore',
    'admin.users.action.revoca_ruolo'        => 'Riporta a utente',
    'admin.users.action.cambia_limite'       => 'Cambia limite',
    'admin.users.action.elimina'             => 'Elimina',

    'admin.users.done.sospendi'            => 'Account :email sospeso: non può più accedere e i suoi blog restano offline.',
    'admin.users.done.riattiva'            => 'Account :email riattivato.',
    'admin.users.done.verifica_email'      => 'Email di :email segnata come verificata.',
    'admin.users.done.promuovi_moderatore' => ':email è ora moderatore.',
    'admin.users.done.promuovi_admin'      => ':email è ora amministratore.',
    'admin.users.done.revoca_ruolo'        => ':email è tornato un utente normale.',
    'admin.users.done.cambia_limite'       => 'Limite di :email portato a :limit blog.',
    'admin.users.done.generic'             => 'Account :email aggiornato.',

    'admin.users.error.not_found'    => 'Questo account non esiste più.',
    'admin.users.error.self_role'    => 'Non puoi cambiare il tuo stesso ruolo: chiedilo a un altro amministratore.',
    'admin.users.error.self_account' => 'Non puoi sospendere né eliminare il tuo account da qui.',
    'admin.users.error.last_admin'   => 'È l\'ultimo amministratore attivo: prima nominane un altro.',
    'admin.users.error.invalid_limit' => 'Il limite di blog deve essere un numero tra 0 e 1000.',

    'admin.users.delete.title'           => 'Elimina :email',
    'admin.users.delete.heading'         => 'Stai per eliminare l\'account :email',
    'admin.users.delete.warning'         => 'L\'operazione è definitiva. Insieme all\'account spariscono tutti i suoi blog, con articoli, file, statistiche e iscritti.',
    'admin.users.delete.item.blogs'      => 'Blog',
    'admin.users.delete.item.posts'      => 'Articoli e pagine',
    'admin.users.delete.item.files'      => 'File caricati',
    'admin.users.delete.item.storage'    => 'Spazio occupato',
    'admin.users.delete.item.subscribers' => 'Iscritti',
    'admin.users.delete.blogs_list'      => 'Blog che verranno eliminati:',
    'admin.users.delete.alternative'     => 'Sospendere l\'account produce lo stesso effetto verso l\'esterno e si può annullare: valutala prima.',
    'admin.users.delete.type_email'      => 'Per confermare, scrivi qui l\'indirizzo email: :email',
    'admin.users.delete.confirm_button'  => 'Elimina definitivamente',
    'admin.users.delete.mismatch'        => 'L\'indirizzo digitato non corrisponde: l\'account non è stato eliminato.',
    'admin.users.delete.done'            => 'Account :email eliminato con tutti i suoi blog.',

    // ---------------------------------------------------------------------
    // Impostazioni
    // ---------------------------------------------------------------------
    'admin.settings.title' => 'Impostazioni della piattaforma',
    'admin.settings.intro' => 'Questi valori si cambiano a caldo e hanno la precedenza su config/config.php. Le impostazioni che possono lasciarti fuori dal pannello — database, dominio, routing, chiavi — restano solo nel file.',

    'admin.settings.section.identity' => 'Identità',
    'admin.settings.section.access'   => 'Accesso e revisione',
    'admin.settings.section.limits'   => 'Limiti predefiniti',
    'admin.settings.section.notice'   => 'Avviso globale',

    'admin.settings.site_name'          => 'Nome del sito',
    'admin.settings.tagline'            => 'Motto',
    'admin.settings.tagline_hint'       => 'Una riga sotto il nome, nella pagina iniziale e nei metadati.',
    'admin.settings.contact_email'      => 'Email di contatto',
    'admin.settings.contact_email_hint' => 'Mostrata nelle pagine pubbliche a chi deve segnalare un abuso, e usata per avvisarti quando un blog nuovo è in attesa di approvazione e quando lo approvi (conferma del terzo livello). Lasciala vuota per non pubblicarla: in quel caso vale l\'indirizzo di config/config.php.',

    'admin.settings.registration_open'      => 'Registrazioni aperte',
    'admin.settings.registration_open_hint' => 'Togliendo la spunta il modulo di registrazione resta raggiungibile ma rifiuta i nuovi account. Gli account esistenti non sono toccati.',
    'admin.settings.verify_email'           => 'Richiedi la verifica dell\'email',
    'admin.settings.verify_email_hint'      => 'Senza verifica non si possono creare blog. È la prima barriera contro le registrazioni automatiche.',
    'admin.settings.review_blogs'           => 'Richiedi la revisione dei nuovi blog',
    'admin.settings.review_blogs_hint'      => 'I blog nuovi restano fuori dagli indici e dalla vetrina finché un moderatore non li approva. Restano comunque leggibili da chi ne ha l\'indirizzo (via percorso sul dominio principale, finché il terzo livello non viene creato).',

    'admin.settings.blogs_per_user'      => 'Blog per utente',
    'admin.settings.blogs_per_user_hint' => 'Vale per i nuovi account; il limite di un singolo utente si cambia dalla pagina Utenti.',
    'admin.settings.posts_per_blog'      => 'Articoli per blog',
    'admin.settings.storage_per_blog'    => 'Spazio per blog (MB)',
    'admin.settings.upload_max'          => 'Dimensione massima di un file (MB)',
    'admin.settings.upload_max_hint'     => 'Il server ha limiti propri (upload_max_filesize e post_max_size): questo valore non può superarli.',

    'admin.settings.notice'      => 'Messaggio di avviso',
    'admin.settings.notice_hint' => 'Compare in cima all\'amministrazione e alla dashboard degli utenti. Lascialo vuoto per non mostrare nulla.',

    'admin.settings.saved'    => 'Impostazioni salvate.',
    'admin.settings.log_note' => 'Impostazioni della piattaforma aggiornate.',
    'admin.settings.file_note' => 'Per svuotare un valore e tornare a quello di config/config.php, cancella il contenuto del campo e salva.',

    'admin.settings.error.name_required'     => 'Il nome del sito non può essere vuoto.',
    'admin.settings.error.contact_email'     => 'L\'email di contatto non sembra un indirizzo valido.',
    'admin.settings.error.limits'            => 'Uno dei limiti è fuori dall\'intervallo consentito.',
    'admin.settings.error.upload_over_quota' => 'Il limite per singolo file non può superare lo spazio totale per blog.',

    // ---------------------------------------------------------------------
    // Registro
    // ---------------------------------------------------------------------
    'admin.log.title'      => 'Registro di moderazione',
    'admin.log.intro'      => 'Chi ha deciso cosa, e quando. Le righe non si cancellano dal pannello.',
    'admin.log.empty'      => 'Il registro è vuoto.',
    'admin.log.system'     => 'sistema',
    'admin.log.col.when'   => 'Quando',
    'admin.log.col.actor'  => 'Chi',
    'admin.log.col.action' => 'Azione',
    'admin.log.col.blog'   => 'Blog',
    'admin.log.col.note'   => 'Nota',
];
