<?php

declare(strict_types=1);

/**
 * Stringhe delle pagine di accesso, registrazione e recupero password.
 *
 * I messaggi di errore sono volutamente generici: nessuno di essi deve
 * permettere di capire se un indirizzo email è registrato su Noblogs.
 */
return [
    // -----------------------------------------------------------------------
    // Accesso
    // -----------------------------------------------------------------------
    'auth.login.title'        => 'Accedi',
    'auth.login.heading'      => 'Accedi al tuo account',
    'auth.login.intro'        => 'Inserisci le credenziali con cui ti sei registrato.',
    'auth.login.email'        => 'Indirizzo email',
    'auth.login.password'     => 'Password',
    'auth.login.submit'       => 'Accedi',
    'auth.login.forgot'       => 'Password dimenticata?',
    'auth.login.no_account'   => 'Non hai ancora un blog?',
    'auth.login.register'     => 'Aprine uno',
    'auth.login.failed'       => 'Email o password non corretti.',
    'auth.login.welcome'      => 'Bentornato.',
    'auth.login.next_notice'  => 'Accedi per continuare.',
    'auth.logout.done'        => 'Sei uscito dal tuo account.',
    'auth.logout.submit'      => 'Esci',

    // -----------------------------------------------------------------------
    // Registrazione
    // -----------------------------------------------------------------------
    'auth.register.title'          => 'Registrati',
    'auth.register.heading'        => 'Apri il tuo blog',
    'auth.register.intro'          => 'Un solo modulo: account e blog nascono insieme. Nessun piano a pagamento, nessuna carta di credito.',
    'auth.register.email'          => 'Indirizzo email',
    'auth.register.email_hint'     => 'Serve per verificare l\'account e recuperare la password. Non lo mostriamo a nessuno.',
    'auth.register.password'       => 'Password',
    'auth.register.password_hint'  => 'Almeno :min caratteri. Meglio una frase lunga che una parola complicata.',
    'auth.register.subdomain'      => 'Indirizzo del blog',
    'auth.register.subdomain_hint' => 'Lettere minuscole, numeri e trattini. Il tuo blog sarà raggiungibile qui.',
    'auth.register.blog_title'     => 'Titolo del blog',
    'auth.register.blog_title_hint' => 'Lo puoi cambiare in qualsiasi momento.',
    'auth.register.submit'         => 'Crea il blog',
    'auth.register.have_account'   => 'Hai già un account?',
    'auth.register.login'          => 'Accedi',
    'auth.register.terms_note'     => 'Registrandoti accetti le condizioni d\'uso e l\'informativa sulla privacy.',
    'auth.register.created'        => 'Blog creato. Controlla la posta per confermare l\'indirizzo email.',
    'auth.register.created_ready'  => 'Blog creato. Ora puoi accedere.',
    'auth.register.review_notice'  => 'I blog appena creati non vengono indicizzati dai motori di ricerca finché un moderatore non li approva. Il blog è comunque online e leggibile da subito.',
    'auth.register.review_path_notice' => 'Finché il blog non è approvato è raggiungibile solo da :domain/nome-scelto. L\'indirizzo di terzo livello si attiva se e quando l\'amministrazione lo abilita.',
    'auth.register.exists_subject' => 'Qualcuno ha provato a registrarsi con il tuo indirizzo',
    'auth.register.exists_body'    => "Ciao,\n\nqualcuno ha provato ad aprire un nuovo account su :site usando questo indirizzo email, che risulta già registrato.\n\nSe sei stato tu, ti basta accedere:\n:login\n\nSe hai dimenticato la password, puoi reimpostarla qui:\n:reset\n\nSe non sei stato tu, non devi fare nulla: nessun account è stato creato e nessuna informazione è stata mostrata a chi ha compilato il modulo.\n",

    // -----------------------------------------------------------------------
    // Verifica dell'indirizzo email
    // -----------------------------------------------------------------------
    'auth.verify.title'         => 'Verifica l\'indirizzo email',
    'auth.verify.heading'       => 'Controlla la posta',
    'auth.verify.body'          => 'Ti abbiamo inviato un messaggio con un link di conferma. Apri il link per attivare del tutto l\'account. Se non arriva entro qualche minuto, controlla la posta indesiderata.',
    'auth.verify.sent_to'       => 'Messaggio inviato a :email.',
    'auth.verify.resend'        => 'Invia di nuovo il messaggio',
    'auth.verify.resent'        => 'Se l\'indirizzo è in attesa di verifica, ti abbiamo inviato un nuovo messaggio.',
    'auth.verify.done'          => 'Indirizzo email confermato. Ora puoi accedere.',
    'auth.verify.invalid_title' => 'Link non valido',
    'auth.verify.invalid_body'  => 'Questo link di conferma non è più valido: può essere già stato usato oppure sostituito da uno più recente. Chiedine un altro dalla pagina di verifica.',
    'auth.verify.email_subject' => 'Conferma il tuo indirizzo su :site',
    'auth.verify.email_body'    => "Ciao,\n\nil tuo blog su :site è pronto: :blog\n\nPer completare la registrazione conferma questo indirizzo email:\n:url\n\nSe non hai chiesto tu questa registrazione, ignora il messaggio: senza conferma l'account resta inutilizzabile.\n",

    // -----------------------------------------------------------------------
    // Password dimenticata e reimpostazione
    // -----------------------------------------------------------------------
    'auth.password.request_title'   => 'Password dimenticata',
    'auth.password.request_heading' => 'Reimposta la password',
    'auth.password.request_intro'   => 'Inserisci l\'indirizzo email del tuo account: ti invieremo un link per scegliere una nuova password.',
    'auth.password.email'           => 'Indirizzo email',
    'auth.password.request_submit'  => 'Invia il link',
    'auth.password.sent'            => 'Se l\'indirizzo corrisponde a un account, il link per reimpostare la password è in arrivo. Vale due ore.',
    'auth.password.reset_title'     => 'Nuova password',
    'auth.password.reset_heading'   => 'Scegli una nuova password',
    'auth.password.reset_intro'     => 'Il link vale una sola volta: dopo il salvataggio non funzionerà più.',
    'auth.password.new'             => 'Nuova password',
    'auth.password.reset_submit'    => 'Salva la password',
    'auth.password.reset_done'      => 'Password aggiornata. Ora puoi accedere.',
    'auth.password.invalid_title'   => 'Link scaduto',
    'auth.password.invalid_body'    => 'Questo link non è più valido: vale due ore e una sola volta. Puoi chiederne un altro.',
    'auth.password.request_again'   => 'Chiedi un nuovo link',
    'auth.password.email_subject'   => 'Reimposta la password su :site',
    'auth.password.email_body'      => "Ciao,\n\nqualcuno ha chiesto di reimpostare la password dell'account :site collegato a questo indirizzo.\n\nSe sei stato tu, scegli una nuova password qui:\n:url\n\nIl link vale due ore e funziona una sola volta.\n\nSe non sei stato tu, ignora il messaggio: la password attuale resta valida e nessuno ha avuto accesso all'account.\n",
    'auth.password.back_to_login'   => 'Torna all\'accesso',

    // -----------------------------------------------------------------------
    // Errori di validazione
    // -----------------------------------------------------------------------
    'auth.error.email_required'    => 'Inserisci il tuo indirizzo email.',
    'auth.error.email_invalid'     => 'Questo indirizzo email non sembra valido.',
    'auth.error.password_required' => 'Inserisci la password.',
    'auth.error.password_short'    => 'La password deve essere lunga almeno :min caratteri.',
    'auth.error.password_long'     => 'La password è troppo lunga: al massimo :max caratteri.',
    'auth.error.password_weak'     => 'Questa password è troppo facile da indovinare. Provane un\'altra, magari una frase di più parole.',
    'auth.error.title_required'    => 'Dai un titolo al blog.',
    'auth.error.title_long'        => 'Il titolo del blog può essere lungo al massimo :max caratteri.',
    'auth.error.generic'           => 'Non è stato possibile completare l\'operazione. Riprova.',
];
