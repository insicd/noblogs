<?php

declare(strict_types=1);

/**
 * Installer web di Noblogs.
 *
 * Viene incluso da public/index.php finché config/config.php non esiste, ma
 * funziona anche se lo si apre direttamente: su parecchi hosting condivisi la
 * document root non si può spostare su public/, e l'unico modo di arrivare qui
 * è /install/index.php.
 *
 * Il file non può contare su niente del resto dell'applicazione dal punto di
 * vista della presentazione — non c'è configurazione, quindi non c'è tema, non
 * c'è layout e non ci sono asset: CSS e testi stanno tutti qui dentro.
 */

// Il bootstrap potrebbe non essere ancora stato eseguito (accesso diretto).
if (!defined('NOBLOGS_ROOT')) {
    require_once dirname(__DIR__) . '/app/bootstrap.php';
}

use Noblogs\Core\Config;
use Noblogs\Core\Database;
use Noblogs\Support\Dates;

// ---------------------------------------------------------------------------
// Costanti e stato
// ---------------------------------------------------------------------------

const NB_INSTALL_STEPS = 5;
const NB_INSTALL_SESSION = 'noblogs_install_state';
const NB_INSTALL_TOKEN = 'noblogs_install_token';
const NB_INSTALL_MIN_PASSWORD = 10;

$configPath = NOBLOGS_ROOT . '/config/config.php';
$selfUrl = strtok((string) ($_SERVER['REQUEST_URI'] ?? '/install/'), '?') ?: '/install/';

/**
 * Reinstallare sopra un'installazione viva significherebbe azzerare i salt e
 * quindi le statistiche, e regalare un account amministratore a chiunque passi
 * di qui. Se la configurazione esiste, l'installer non parte: punto.
 */
if (is_file($configPath)) {
    nb_install_page('Installazione già eseguita', 0, static function (): void {
        ?>
        <p class="nb-lead">Noblogs risulta già installato su questo server: il file
          <code>config/config.php</code> esiste.</p>
        <div class="nb-box nb-box--warn">
          <p><strong>Cancella subito la cartella <code>install/</code>.</strong> Lasciarla sul
          server non serve a nulla e resta una porta in più.</p>
        </div>
        <p>Se devi reinstallare da zero, elimina o rinomina <code>config/config.php</code>
        e ricarica questa pagina. Se invece hai perso la password, usa dalla riga di
        comando <code>php bin/noblogs crea-admin tua@email.it</code>.</p>
        <p><a class="nb-btn nb-btn--primary" href="/accedi">Vai all'accesso</a></p>
        <?php
    });
    exit;
}

// Sessione dedicata: quella dell'applicazione userebbe il cookie sicuro anche
// su http, e su un server ancora da configurare sarebbe perso.
if (session_status() !== PHP_SESSION_ACTIVE) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

    session_name('noblogs_install');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

if (!isset($_SESSION[NB_INSTALL_TOKEN]) || !is_string($_SESSION[NB_INSTALL_TOKEN])) {
    $_SESSION[NB_INSTALL_TOKEN] = bin2hex(random_bytes(32));
}
$token = (string) $_SESSION[NB_INSTALL_TOKEN];

/** @var array<string,mixed> $state Dati raccolti finora, conservati tra i passi. */
$state = is_array($_SESSION[NB_INSTALL_SESSION] ?? null) ? $_SESSION[NB_INSTALL_SESSION] : [];

$isPost = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
$errors = [];
$notices = [];

/** @var array<string,mixed>|null $lastResult Esito appena prodotto, non ancora in sessione. */
$lastResult = null;

$requirements = nb_install_requirements();
$requirementsOk = !in_array(false, array_column(array_filter(
    $requirements,
    static fn(array $r): bool => $r['required']
), 'ok'), true);

// Il passo raggiungibile dipende da cosa è già stato completato: ricaricare a
// metà o cambiare il numero a mano non deve poter saltare una fase.
$maxStep = 1;
if ($requirementsOk) {
    $maxStep = 2;
}
if ($maxStep >= 2 && isset($state['db'])) {
    $maxStep = 3;
}
if ($maxStep >= 3 && isset($state['site'])) {
    $maxStep = 4;
}
if ($maxStep >= 4 && isset($state['admin'])) {
    $maxStep = 5;
}

$requested = (int) ($_GET['passo'] ?? ($isPost ? ($_POST['passo'] ?? 1) : 1));
$step = max(1, min($maxStep, $requested));

// ---------------------------------------------------------------------------
// Elaborazione dei moduli (prima di qualunque output, per poter redirigere)
// ---------------------------------------------------------------------------

if ($isPost) {
    $submitted = (string) ($_POST['_token'] ?? '');
    if (!hash_equals($token, $submitted)) {
        $errors[] = 'La sessione è scaduta. Ricarica la pagina e riprova.';
    } else {
        $action = (string) ($_POST['azione'] ?? '');

        switch ((int) ($_POST['passo'] ?? 0)) {
            case 2:
                [$state, $errors, $notices, $advance] = nb_install_handle_database($state, $_POST, $action);
                if ($advance) {
                    nb_install_save($state);
                    nb_install_redirect($selfUrl, 3);
                }
                $step = 2;
                break;

            case 3:
                [$state, $errors] = nb_install_handle_site($state, $_POST);
                if ($errors === []) {
                    nb_install_save($state);
                    nb_install_redirect($selfUrl, 4);
                }
                $step = 3;
                break;

            case 4:
                [$state, $errors] = nb_install_handle_admin($state, $_POST);
                if ($errors === []) {
                    nb_install_save($state);
                    nb_install_redirect($selfUrl, 5);
                }
                $step = 4;
                break;

            case 5:
                if ($action === 'installa' && isset($state['db'], $state['site'], $state['admin'])) {
                    $result = nb_install_run($state, $configPath);
                    // L'esito si conserva solo quando resta qualcosa da fare:
                    // il testo di config.php da incollare a mano non deve
                    // sparire se la pagina viene ricaricata. A installazione
                    // completa non si conserva nulla, così una ricarica non
                    // mostra mai un riepilogo che non corrisponde più.
                    $state['result'] = $result['config_written'] ? null : $result;
                    nb_install_save($state);
                    $lastResult = $result;
                }
                $step = 5;
                break;
        }
    }
}

nb_install_save($state);

// ---------------------------------------------------------------------------
// Presentazione
// ---------------------------------------------------------------------------

$titles = [
    1 => 'Requisiti del server',
    2 => 'Database',
    3 => 'Piattaforma',
    4 => 'Amministratore',
    5 => 'Installazione',
];

nb_install_page($titles[$step], $step, static function () use (
    $step,
    $state,
    $errors,
    $notices,
    $requirements,
    $requirementsOk,
    $selfUrl,
    $token,
    $configPath,
    $lastResult
): void {
    foreach ($errors as $error) {
        echo '<p class="nb-msg nb-msg--error">' . nb_e($error) . '</p>';
    }
    foreach ($notices as $notice) {
        echo '<p class="nb-msg nb-msg--ok">' . nb_e($notice) . '</p>';
    }

    match ($step) {
        1       => nb_install_view_requirements($requirements, $requirementsOk, $selfUrl),
        2       => nb_install_view_database($state, $selfUrl, $token),
        3       => nb_install_view_site($state, $selfUrl, $token),
        4       => nb_install_view_admin($state, $selfUrl, $token),
        default => nb_install_view_run($state, $selfUrl, $token, $configPath, $lastResult),
    };
});

exit;

// ===========================================================================
// Funzioni di supporto
// ===========================================================================

function nb_e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** @param array<string,mixed> $state */
function nb_install_save(array $state): void
{
    $_SESSION[NB_INSTALL_SESSION] = $state;
}

function nb_install_redirect(string $self, int $step): never
{
    header('Location: ' . $self . '?passo=' . $step, true, 303);
    exit;
}

/**
 * Requisiti del server.
 *
 * @return list<array{label:string,ok:bool,required:bool,detail:string,fix:string}>
 */
function nb_install_requirements(): array
{
    $checks = [];

    $checks[] = [
        'label'    => 'PHP 8.1 o superiore',
        'ok'       => PHP_VERSION_ID >= 80100,
        'required' => true,
        'detail'   => 'Versione rilevata: ' . PHP_VERSION,
        'fix'      => 'Nei pannelli di hosting la versione di PHP si cambia da «Select PHP Version» '
                    . '(cPanel), «Impostazioni PHP» (Plesk) o «PHP Selector» (DirectAdmin).',
    ];

    $required = [
        'pdo_mysql' => 'Connessione a MySQL o MariaDB.',
        'mbstring'  => 'Gestione corretta dei testi in UTF-8.',
        'json'      => 'Codifica dei tag e delle liste interne.',
    ];
    foreach ($required as $extension => $why) {
        $checks[] = [
            'label'    => 'Estensione ' . $extension,
            'ok'       => extension_loaded($extension),
            'required' => true,
            'detail'   => $why,
            'fix'      => 'Attivala dal pannello di hosting, nella pagina delle estensioni PHP, '
                        . 'oppure con «apt install php-' . str_replace('pdo_', '', $extension) . '» su un server tuo.',
        ];
    }

    $optional = [
        'gd'       => 'Ridimensionamento delle immagini e rimozione dei metadati EXIF (compresa la posizione GPS).',
        'dom'      => 'Bonifica dell\'HTML nei contenuti.',
        'zip'      => 'Esportazione di un blog in un unico archivio.',
        'intl'     => 'Traslitterazione degli indirizzi con caratteri non latini.',
        'fileinfo' => 'Riconoscimento del tipo reale dei file caricati.',
    ];
    foreach ($optional as $extension => $why) {
        $checks[] = [
            'label'    => 'Estensione ' . $extension,
            'ok'       => extension_loaded($extension),
            'required' => false,
            'detail'   => $why,
            'fix'      => 'Senza questa estensione Noblogs funziona lo stesso, con qualche comodità in meno.',
        ];
    }

    foreach ([
        'storage'      => NOBLOGS_ROOT . '/storage',
        'public/media' => NOBLOGS_ROOT . '/public/media',
        'config'       => NOBLOGS_ROOT . '/config',
    ] as $label => $path) {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
        $writable = is_dir($path) && is_writable($path);

        $checks[] = [
            'label'    => 'Cartella ' . $label . ' scrivibile',
            'ok'       => $writable,
            // config/ non è obbligatoria: se non è scrivibile mostriamo il file
            // da incollare a mano, che su hosting condiviso è la norma.
            'required' => $label !== 'config',
            'detail'   => $path,
            'fix'      => 'Da riga di comando: «chmod 755 ' . $path . '» (oppure 775 se il web server '
                        . 'gira con un utente diverso dal tuo). Dal file manager del pannello: tasto destro '
                        . 'sulla cartella → «Permessi» → 755, con la spunta su «scrittura» per il proprietario.',
        ];
    }

    $checks[] = [
        'label'    => 'Schema del database leggibile',
        'ok'       => is_readable(NOBLOGS_ROOT . '/db/schema.sql'),
        'required' => true,
        'detail'   => 'db/schema.sql',
        'fix'      => 'Il caricamento via FTP è incompleto: ricarica la cartella db/.',
    ];

    return $checks;
}

/**
 * Passo 2: raccolta e verifica dei parametri del database.
 *
 * @param array<string,mixed> $state
 * @param array<string,mixed> $post
 * @return array{0:array<string,mixed>,1:list<string>,2:list<string>,3:bool}
 */
function nb_install_handle_database(array $state, array $post, string $action): array
{
    $params = [
        'host'     => trim((string) ($post['db_host'] ?? 'localhost')),
        'port'     => (int) ($post['db_port'] ?? 3306),
        'name'     => trim((string) ($post['db_name'] ?? '')),
        'user'     => trim((string) ($post['db_user'] ?? '')),
        'password' => (string) ($post['db_password'] ?? ''),
        'prefix'   => trim((string) ($post['db_prefix'] ?? '')),
    ];

    // I dati restano nel modulo anche se la prova fallisce.
    $state['db_draft'] = $params;

    $errors = [];
    $notices = [];

    if ($params['host'] === '') {
        $errors[] = 'L\'host del database non può essere vuoto (di solito è «localhost»).';
    }
    if ($params['port'] < 1 || $params['port'] > 65535) {
        $errors[] = 'La porta deve essere un numero tra 1 e 65535 (di solito 3306).';
    }
    if ($params['name'] === '') {
        $errors[] = 'Indica il nome del database.';
    }
    if ($params['user'] === '') {
        $errors[] = 'Indica l\'utente del database.';
    }
    // Il prefisso finisce dentro i nomi di tabella nello schema, che non può
    // essere parametrizzato: qui si accetta solo ciò che è sicuro per certo.
    if (!preg_match('/^[A-Za-z0-9_]{0,32}$/', $params['prefix'])) {
        $errors[] = 'Il prefisso può contenere solo lettere, numeri e trattini bassi (massimo 32 caratteri).';
    }

    if ($errors !== []) {
        return [$state, $errors, $notices, false];
    }

    try {
        $db = Database::connect($params + ['socket' => null]);

        // Non «SHOW TABLES LIKE ?»: su alcune versioni di MySQL quella forma
        // non si lascia preparare lato server. information_schema sì.
        $existing = $db->pdo()->prepare(
            'SELECT COUNT(*) FROM information_schema.tables
             WHERE table_schema = ? AND table_name LIKE ?'
        );
        $existing->execute([$params['name'], str_replace('_', '\\_', $params['prefix']) . '%']);
        $tables = (int) $existing->fetchColumn();

        $notices[] = 'Connessione riuscita al database «' . $params['name'] . '».';
        if ($tables > 0) {
            $notices[] = 'Attenzione: il database contiene già ' . $tables
                . ' tabelle con questo prefisso. Le tabelle esistenti non verranno toccate, ma se '
                . 'appartengono a un\'altra installazione di Noblogs conviene cambiare prefisso.';
        }
    } catch (\Throwable $e) {
        return [
            $state,
            ['Connessione non riuscita: ' . $e->getMessage()],
            [],
            false,
        ];
    }

    if ($action === 'avanti') {
        $state['db'] = $params;
        return [$state, [], $notices, true];
    }

    return [$state, [], $notices, false];
}

/**
 * Passo 3: parametri della piattaforma.
 *
 * @param array<string,mixed> $state
 * @param array<string,mixed> $post
 * @return array{0:array<string,mixed>,1:list<string>}
 */
function nb_install_handle_site(array $state, array $post): array
{
    $site = [
        'domain'        => mb_strtolower(trim((string) ($post['domain'] ?? ''))),
        'name'          => trim((string) ($post['name'] ?? '')),
        'tagline'       => trim((string) ($post['tagline'] ?? '')),
        'https'         => isset($post['https']),
        'mode'          => ($post['mode'] ?? 'subdomain') === 'path' ? 'path' : 'subdomain',
        'path_fallback' => isset($post['path_fallback']),
        'timezone'      => (string) ($post['timezone'] ?? 'Europe/Rome'),
        'locale'        => (string) ($post['locale'] ?? 'it'),
        'email_from'    => trim((string) ($post['email_from'] ?? '')),
        'contact_email' => trim((string) ($post['contact_email'] ?? '')),
    ];

    $state['site_draft'] = $site;
    $errors = [];

    // Si accetta anche un dominio con la porta (utile in locale), ma solo la
    // parte host finisce nella configurazione.
    $site['domain'] = (string) preg_replace('/:\d+$/', '', $site['domain']);

    if ($site['domain'] === '' || !preg_match('/^[a-z0-9]([a-z0-9.-]*[a-z0-9])?$/', $site['domain'])) {
        $errors[] = 'Il dominio principale non è valido. Scrivilo senza «https://» e senza barra finale, per esempio «miosito.it».';
    }
    if ($site['name'] === '') {
        $errors[] = 'Dai un nome alla piattaforma.';
    }
    if (!in_array($site['timezone'], \DateTimeZone::listIdentifiers(), true)) {
        $errors[] = 'Fuso orario non riconosciuto.';
    }
    if (!in_array($site['locale'], nb_install_locales(), true)) {
        $errors[] = 'Lingua non disponibile.';
    }
    if ($site['email_from'] === '' || !filter_var($site['email_from'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'indirizzo mittente non è valido.';
    }
    if ($site['contact_email'] !== '' && !filter_var($site['contact_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'L\'indirizzo di contatto non è valido.';
    }

    if ($errors === []) {
        $state['site'] = $site;
        $state['site_draft'] = $site;
    }

    return [$state, $errors];
}

/**
 * Passo 4: primo account amministratore.
 *
 * La password non viene conservata in sessione: si cifra subito e in sessione
 * finisce solo l'hash.
 *
 * @param array<string,mixed> $state
 * @param array<string,mixed> $post
 * @return array{0:array<string,mixed>,1:list<string>}
 */
function nb_install_handle_admin(array $state, array $post): array
{
    $email = mb_strtolower(trim((string) ($post['admin_email'] ?? '')));
    $password = (string) ($post['admin_password'] ?? '');
    $confirm = (string) ($post['admin_password_confirm'] ?? '');

    $state['admin_draft'] = ['email' => $email];
    $errors = [];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 191) {
        $errors[] = 'L\'indirizzo email non è valido.';
    }
    if (mb_strlen($password) < NB_INSTALL_MIN_PASSWORD) {
        $errors[] = 'La password deve essere lunga almeno ' . NB_INSTALL_MIN_PASSWORD . ' caratteri.';
    }
    if ($password !== $confirm) {
        $errors[] = 'Le due password non coincidono.';
    }

    if ($errors === []) {
        $state['admin'] = [
            'email' => $email,
            'hash'  => password_hash($password, PASSWORD_DEFAULT),
        ];
    }

    return [$state, $errors];
}

/**
 * Esecuzione dell'installazione.
 *
 * L'ordine conta: prima le tabelle e l'amministratore, poi il file di
 * configurazione. Se il file non si può scrivere il lavoro è comunque fatto e
 * all'utente resta solo da incollare un testo.
 *
 * @param array<string,mixed> $state
 * @return array{ok:bool,steps:list<array{label:string,ok:bool,detail:string}>,config:string,config_written:bool}
 */
function nb_install_run(array $state, string $configPath): array
{
    /** @var array{host:string,port:int,name:string,user:string,password:string,prefix:string} $dbParams */
    $dbParams = $state['db'];
    /** @var array<string,mixed> $site */
    $site = $state['site'];
    /** @var array{email:string,hash:string} $admin */
    $admin = $state['admin'];

    $steps = [];
    $config = nb_install_config($dbParams, $site);
    $configText = Config::export($config);

    try {
        $db = Database::connect($dbParams + ['socket' => null]);

        // ---- Tabelle ----
        $schema = (string) file_get_contents(NOBLOGS_ROOT . '/db/schema.sql');
        $schema = str_replace('{{prefix}}', $dbParams['prefix'], $schema);
        $schema = (string) preg_replace('/^\s*--.*$/m', '', $schema);

        $created = 0;
        foreach (explode(';', $schema) as $statement) {
            $statement = trim($statement);
            if ($statement === '') {
                continue;
            }
            // DDL: non ammette parametri legati. L'unico valore variabile è il
            // prefisso, già ristretto a [A-Za-z0-9_] al passo precedente.
            $db->pdo()->exec($statement);
            $created++;
        }
        $steps[] = ['label' => 'Tabelle create', 'ok' => true, 'detail' => $created . ' istruzioni eseguite'];

        // Da qui in poi i modelli devono poter lavorare: si applica la
        // configurazione appena costruita senza aspettare che il file esista.
        foreach ($config as $key => $value) {
            Config::set((string) $key, $value);
        }
        Database::setInstance($db);

        // ---- Temi ----
        $themes = \Noblogs\Models\Theme::sync();
        $steps[] = ['label' => 'Temi installati', 'ok' => true, 'detail' => $themes . ' temi'];

        // ---- Amministratore ----
        $existing = $db->fetch('SELECT id FROM {{users}} WHERE email = ?', [$admin['email']]);
        if ($existing === null) {
            $db->query(
                'INSERT INTO {{users}} (email, password_hash, role, is_active, max_blogs, locale,
                                        timezone, email_verified_at, created_at)
                 VALUES (:email, :hash, :role, 1, :max_blogs, :locale, :timezone, :verified, :created)',
                [
                    'email'     => $admin['email'],
                    'hash'      => $admin['hash'],
                    'role'      => 'admin',
                    'max_blogs' => (int) ($config['limits']['blogs_per_user'] ?? 3),
                    'locale'    => $site['locale'],
                    'timezone'  => $site['timezone'],
                    'verified'  => Dates::nowString(),
                    'created'   => Dates::nowString(),
                ]
            );
            $detail = 'nuovo account ' . $admin['email'];
        } else {
            // Rilanciare l'installer su un database già popolato non deve
            // creare un doppione: l'account esistente viene promosso.
            $db->query(
                'UPDATE {{users}} SET password_hash = :hash, role = :role, is_active = 1,
                        email_verified_at = COALESCE(email_verified_at, :verified), verify_token = NULL
                 WHERE id = :id',
                [
                    'hash'     => $admin['hash'],
                    'role'     => 'admin',
                    'verified' => Dates::nowString(),
                    'id'       => (int) $existing['id'],
                ]
            );
            $detail = 'account esistente promosso ad amministratore';
        }
        $steps[] = ['label' => 'Amministratore', 'ok' => true, 'detail' => $detail];
    } catch (\Throwable $e) {
        $steps[] = ['label' => 'Errore', 'ok' => false, 'detail' => $e->getMessage()];
        return ['ok' => false, 'steps' => $steps, 'config' => $configText, 'config_written' => false];
    }

    // ---- File di configurazione ----
    $written = false;
    if (is_writable(dirname($configPath))) {
        $written = file_put_contents($configPath, $configText, LOCK_EX) !== false;
        if ($written) {
            // Contiene la password del database: non deve essere leggibile da
            // altri utenti dello stesso server.
            chmod($configPath, 0640);
        }
    }
    $steps[] = [
        'label'  => 'File config/config.php',
        'ok'     => $written,
        'detail' => $written ? 'scritto' : 'non scrivibile: va creato a mano',
    ];

    return ['ok' => true, 'steps' => $steps, 'config' => $configText, 'config_written' => $written];
}

/**
 * Costruisce la configurazione finale a partire dal file di esempio.
 *
 * @param array{host:string,port:int,name:string,user:string,password:string,prefix:string} $db
 * @param array<string,mixed> $site
 * @return array<string,mixed>
 */
function nb_install_config(array $db, array $site): array
{
    /** @var array<string,mixed> $config */
    $config = require NOBLOGS_ROOT . '/config/config.sample.php';

    $config['db'] = [
        'host'     => $db['host'],
        'port'     => $db['port'],
        'name'     => $db['name'],
        'user'     => $db['user'],
        'password' => $db['password'],
        'prefix'   => $db['prefix'],
        'socket'   => null,
    ];

    $config['site']['domain'] = $site['domain'];
    $config['site']['name'] = $site['name'];
    $config['site']['tagline'] = $site['tagline'];
    $config['site']['https'] = (bool) $site['https'];
    $config['site']['email_from'] = $site['email_from'];
    $config['site']['email_name'] = $site['name'];
    $config['site']['contact_email'] = $site['contact_email'] !== '' ? $site['contact_email'] : $site['email_from'];
    $config['site']['locale'] = $site['locale'];
    $config['site']['timezone'] = $site['timezone'];

    $config['routing']['mode'] = $site['mode'];
    $config['routing']['path_fallback'] = $site['mode'] === 'path' ? true : (bool) $site['path_fallback'];

    // Salt casuali: sono l'unica cosa che rende non reversibili gli hash delle
    // statistiche e non falsificabili i link firmati.
    $config['security']['analytics_salt'] = bin2hex(random_bytes(32));
    $config['security']['app_key'] = bin2hex(random_bytes(32));

    $config['debug'] = false;

    return $config;
}

/** @return list<string> */
function nb_install_locales(): array
{
    $locales = [];
    foreach (glob(NOBLOGS_ROOT . '/lang/*', GLOB_ONLYDIR) ?: [] as $directory) {
        $locales[] = basename($directory);
    }
    sort($locales);
    return $locales !== [] ? $locales : ['it'];
}

// ===========================================================================
// Viste
// ===========================================================================

/**
 * @param list<array{label:string,ok:bool,required:bool,detail:string,fix:string}> $requirements
 */
function nb_install_view_requirements(array $requirements, bool $ok, string $self): void
{
    ?>
    <p class="nb-lead">Prima di cominciare, controlliamo che il server abbia tutto il
    necessario. Le voci contrassegnate come consigliate non bloccano l'installazione.</p>

    <table class="nb-table">
      <?php foreach ($requirements as $check): ?>
        <tr class="<?= $check['ok'] ? 'is-ok' : ($check['required'] ? 'is-bad' : 'is-warn') ?>">
          <td class="nb-table__mark"><?= $check['ok'] ? '&check;' : ($check['required'] ? '&times;' : '!') ?></td>
          <td>
            <strong><?= nb_e($check['label']) ?></strong>
            <?php if (!$check['required']): ?><span class="nb-pill">consigliata</span><?php endif; ?>
            <div class="nb-small"><?= nb_e($check['detail']) ?></div>
            <?php if (!$check['ok']): ?>
              <div class="nb-fix"><?= nb_e($check['fix']) ?></div>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
    </table>

    <?php if ($ok): ?>
      <p><a class="nb-btn nb-btn--primary" href="<?= nb_e($self) ?>?passo=2">Continua</a></p>
    <?php else: ?>
      <div class="nb-box nb-box--warn">
        <p>Manca almeno un requisito obbligatorio: sistemalo e ricarica la pagina.
        Non si può proseguire, perché l'installazione fallirebbe a metà lasciando
        un database incompleto.</p>
      </div>
      <p><a class="nb-btn" href="<?= nb_e($self) ?>?passo=1">Ricontrolla</a></p>
    <?php endif; ?>
    <?php
}

/** @param array<string,mixed> $state */
function nb_install_view_database(array $state, string $self, string $token): void
{
    $draft = is_array($state['db_draft'] ?? null) ? $state['db_draft'] : [];
    $value = static fn(string $key, string $default): string => nb_e((string) ($draft[$key] ?? $default));
    ?>
    <p class="nb-lead">Noblogs ha bisogno di un database MySQL o MariaDB già creato, e di un
    utente che abbia i permessi per crearci dentro le tabelle. Sui pannelli di hosting
    entrambi si creano dalla sezione «Database MySQL».</p>

    <form method="post" action="<?= nb_e($self) ?>">
      <input type="hidden" name="_token" value="<?= nb_e($token) ?>">
      <input type="hidden" name="passo" value="2">

      <div class="nb-grid">
        <label>Host
          <input type="text" name="db_host" value="<?= $value('host', 'localhost') ?>" required>
          <span class="nb-small">Quasi sempre «localhost». Alcuni hosting indicano un nome diverso.</span>
        </label>
        <label>Porta
          <input type="number" name="db_port" value="<?= $value('port', '3306') ?>" min="1" max="65535" required>
        </label>
      </div>

      <label>Nome del database
        <input type="text" name="db_name" value="<?= $value('name', '') ?>" required>
      </label>

      <div class="nb-grid">
        <label>Utente
          <input type="text" name="db_user" value="<?= $value('user', '') ?>" required autocomplete="off">
        </label>
        <label>Password
          <input type="password" name="db_password" value="<?= $value('password', '') ?>" autocomplete="new-password">
        </label>
      </div>

      <label>Prefisso delle tabelle
        <input type="text" name="db_prefix" value="<?= $value('prefix', 'nb_') ?>" maxlength="32" pattern="[A-Za-z0-9_]*">
        <span class="nb-small">Serve solo se il database è condiviso con altre applicazioni.
        Lettere, numeri e trattini bassi.</span>
      </label>

      <p>
        <button type="submit" name="azione" value="prova" class="nb-btn">Prova la connessione</button>
        <button type="submit" name="azione" value="avanti" class="nb-btn nb-btn--primary">Verifica e continua</button>
      </p>
      <p class="nb-small">«Verifica e continua» prova comunque la connessione: non si prosegue
      con parametri che non funzionano.</p>
    </form>
    <?php
}

/** @param array<string,mixed> $state */
function nb_install_view_site(array $state, string $self, string $token): void
{
    $draft = is_array($state['site_draft'] ?? null) ? $state['site_draft'] : [];
    $host = (string) preg_replace('/:\d+$/', '', (string) ($_SERVER['HTTP_HOST'] ?? 'localhost'));
    $guessHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';

    $domain = (string) ($draft['domain'] ?? $host);
    $mode = (string) ($draft['mode'] ?? 'subdomain');
    $https = array_key_exists('https', $draft) ? (bool) $draft['https'] : $guessHttps;
    $fallback = array_key_exists('path_fallback', $draft) ? (bool) $draft['path_fallback'] : true;
    ?>
    <form method="post" action="<?= nb_e($self) ?>">
      <input type="hidden" name="_token" value="<?= nb_e($token) ?>">
      <input type="hidden" name="passo" value="3">

      <label>Dominio principale
        <input type="text" name="domain" value="<?= nb_e($domain) ?>" required>
        <span class="nb-small">Senza «https://» e senza barra finale. È il dominio su cui vivono
        la pagina iniziale, la registrazione e le dashboard.</span>
      </label>

      <div class="nb-grid">
        <label>Nome della piattaforma
          <input type="text" name="name" value="<?= nb_e((string) ($draft['name'] ?? 'Noblogs')) ?>" required maxlength="100">
        </label>
        <label>Motto
          <input type="text" name="tagline" value="<?= nb_e((string) ($draft['tagline'] ?? 'Blog leggeri, senza tracciamento.')) ?>" maxlength="200">
        </label>
      </div>

      <label class="nb-check">
        <input type="checkbox" name="https" value="1" <?= $https ? 'checked' : '' ?>>
        Il sito è raggiungibile in HTTPS
        <span class="nb-small">Lascia la spunta se hai un certificato attivo. Con HTTPS i cookie di
        sessione vengono marcati come sicuri: se il sito è in HTTP e la spunta resta, non riuscirai
        più ad accedere.</span>
      </label>

      <fieldset class="nb-fieldset">
        <legend>Come si raggiungono i blog</legend>

        <label class="nb-check">
          <input type="radio" name="mode" value="subdomain" <?= $mode === 'subdomain' ? 'checked' : '' ?>>
          Sottodominio — <code>miablog.<?= nb_e($domain) ?></code>
        </label>
        <p class="nb-small nb-indent">
          Richiede due cose sul server, entrambe da fare una volta sola:
          un record DNS jolly <code>*.<?= nb_e($domain) ?></code> che punti all'indirizzo IP di questo
          server (nel pannello del registrar: tipo A, nome <code>*</code>), e un virtual host jolly che
          accetti qualunque sottodominio. Su Apache si aggiunge
          <code>ServerAlias *.<?= nb_e($domain) ?></code> al vhost; su nginx si usa
          <code>server_name <?= nb_e($domain) ?> *.<?= nb_e($domain) ?>;</code>. Il file
          <code>noblogs.nginx.conf</code> nella radice del progetto è un esempio pronto.
          Se il sito è in HTTPS serve anche un certificato jolly.
        </p>

        <label class="nb-check">
          <input type="radio" name="mode" value="path" <?= $mode === 'path' ? 'checked' : '' ?>>
          Percorso — <code><?= nb_e($domain) ?>/miablog/</code>
        </label>
        <p class="nb-small nb-indent">
          Non richiede niente: né DNS jolly, né vhost jolly, né certificato jolly.
          È la scelta giusta su hosting condiviso e per provare.
        </p>

        <label class="nb-check">
          <input type="checkbox" name="path_fallback" value="1" <?= $fallback ? 'checked' : '' ?>>
          In modalità sottodominio, accetta anche gli indirizzi per percorso
          <span class="nb-small">Rete di sicurezza: i blog restano raggiungibili anche se il DNS jolly
          non è ancora attivo. L'indirizzo canonico resta il sottodominio.</span>
        </label>
      </fieldset>

      <div class="nb-grid">
        <label>Fuso orario
          <select name="timezone">
            <?php $selected = (string) ($draft['timezone'] ?? 'Europe/Rome'); ?>
            <?php foreach (\DateTimeZone::listIdentifiers() as $zone): ?>
              <option value="<?= nb_e($zone) ?>" <?= $zone === $selected ? 'selected' : '' ?>><?= nb_e($zone) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
        <label>Lingua predefinita
          <select name="locale">
            <?php $selectedLocale = (string) ($draft['locale'] ?? 'it'); ?>
            <?php foreach (nb_install_locales() as $locale): ?>
              <option value="<?= nb_e($locale) ?>" <?= $locale === $selectedLocale ? 'selected' : '' ?>><?= nb_e($locale) ?></option>
            <?php endforeach; ?>
          </select>
        </label>
      </div>

      <div class="nb-grid">
        <label>Email mittente
          <input type="email" name="email_from" value="<?= nb_e((string) ($draft['email_from'] ?? 'noreply@' . $domain)) ?>" required>
          <span class="nb-small">Da questo indirizzo partono le conferme e i recuperi password.</span>
        </label>
        <label>Email di contatto
          <input type="email" name="contact_email" value="<?= nb_e((string) ($draft['contact_email'] ?? 'admin@' . $domain)) ?>">
          <span class="nb-small">Mostrata nelle pagine pubbliche. Se la lasci vuota viene usata quella del mittente.</span>
        </label>
      </div>

      <p>
        <a class="nb-btn" href="<?= nb_e($self) ?>?passo=2">Indietro</a>
        <button type="submit" class="nb-btn nb-btn--primary">Continua</button>
      </p>
    </form>
    <?php
}

/** @param array<string,mixed> $state */
function nb_install_view_admin(array $state, string $self, string $token): void
{
    $draft = is_array($state['admin_draft'] ?? null) ? $state['admin_draft'] : [];
    ?>
    <p class="nb-lead">Questo è il primo account: viene creato come amministratore e con
    l'email già verificata, così puoi entrare subito.</p>

    <form method="post" action="<?= nb_e($self) ?>">
      <input type="hidden" name="_token" value="<?= nb_e($token) ?>">
      <input type="hidden" name="passo" value="4">

      <label>Email
        <input type="email" name="admin_email" value="<?= nb_e((string) ($draft['email'] ?? '')) ?>" required autocomplete="username">
      </label>

      <div class="nb-grid">
        <label>Password
          <input type="password" name="admin_password" required minlength="<?= NB_INSTALL_MIN_PASSWORD ?>" autocomplete="new-password">
          <span class="nb-small">Almeno <?= NB_INSTALL_MIN_PASSWORD ?> caratteri. Una frase lunga è più
          robusta e più facile da ricordare di una parola con i simboli.</span>
        </label>
        <label>Ripeti la password
          <input type="password" name="admin_password_confirm" required minlength="<?= NB_INSTALL_MIN_PASSWORD ?>" autocomplete="new-password">
        </label>
      </div>

      <p>
        <a class="nb-btn" href="<?= nb_e($self) ?>?passo=3">Indietro</a>
        <button type="submit" class="nb-btn nb-btn--primary">Continua</button>
      </p>
    </form>
    <?php
}

/**
 * @param array<string,mixed>      $state
 * @param array<string,mixed>|null $lastResult Esito dell'installazione appena eseguita.
 */
function nb_install_view_run(array $state, string $self, string $token, string $configPath, ?array $lastResult = null): void
{
    $result = $lastResult ?? (is_array($state['result'] ?? null) ? $state['result'] : null);

    if ($result === null) {
        $site = is_array($state['site'] ?? null) ? $state['site'] : [];
        $db = is_array($state['db'] ?? null) ? $state['db'] : [];
        $admin = is_array($state['admin'] ?? null) ? $state['admin'] : [];
        ?>
        <p class="nb-lead">Ultimo controllo prima di scrivere qualcosa. Da qui in poi vengono
        create le tabelle, installati i temi e creato il tuo account.</p>

        <table class="nb-table nb-table--recap">
          <tr><th>Database</th><td><?= nb_e((string) ($db['name'] ?? '')) ?> su <?= nb_e((string) ($db['host'] ?? '')) ?>, prefisso «<?= nb_e((string) ($db['prefix'] ?? '')) ?>»</td></tr>
          <tr><th>Dominio</th><td><?= nb_e((string) ($site['domain'] ?? '')) ?></td></tr>
          <tr><th>Blog raggiungibili per</th><td><?= ($site['mode'] ?? '') === 'path' ? 'percorso' : 'sottodominio' ?></td></tr>
          <tr><th>Amministratore</th><td><?= nb_e((string) ($admin['email'] ?? '')) ?></td></tr>
        </table>

        <form method="post" action="<?= nb_e($self) ?>">
          <input type="hidden" name="_token" value="<?= nb_e($token) ?>">
          <input type="hidden" name="passo" value="5">
          <p>
            <a class="nb-btn" href="<?= nb_e($self) ?>?passo=4">Indietro</a>
            <button type="submit" name="azione" value="installa" class="nb-btn nb-btn--primary">Installa</button>
          </p>
        </form>
        <?php
        return;
    }

    ?>
    <table class="nb-table">
      <?php foreach ($result['steps'] as $entry): ?>
        <tr class="<?= $entry['ok'] ? 'is-ok' : 'is-bad' ?>">
          <td class="nb-table__mark"><?= $entry['ok'] ? '&check;' : '&times;' ?></td>
          <td><strong><?= nb_e($entry['label']) ?></strong><div class="nb-small"><?= nb_e($entry['detail']) ?></div></td>
        </tr>
      <?php endforeach; ?>
    </table>
    <?php

    if (!$result['ok']) {
        ?>
        <div class="nb-box nb-box--warn">
          <p>L'installazione si è fermata. Correggi il problema segnalato e riprova:
          le tabelle già create non danno fastidio, lo schema le ricrea solo se mancano.</p>
        </div>
        <form method="post" action="<?= nb_e($self) ?>">
          <input type="hidden" name="_token" value="<?= nb_e($token) ?>">
          <input type="hidden" name="passo" value="5">
          <p><button type="submit" name="azione" value="installa" class="nb-btn nb-btn--primary">Riprova</button></p>
        </form>
        <?php
        return;
    }

    if (!$result['config_written']) {
        ?>
        <div class="nb-box nb-box--warn">
          <h2>Manca solo il file di configurazione</h2>
          <p>La cartella <code>config/</code> non è scrivibile dal server web — è la situazione
          più comune sugli hosting condivisi, e si risolve in un minuto:</p>
          <ol>
            <li>Copia tutto il testo qui sotto.</li>
            <li>Crea un file nuovo chiamato <code>config.php</code> dentro la cartella
            <code>config/</code>, con il file manager del pannello o con il tuo client FTP.</li>
            <li>Incollaci dentro il testo e salvalo. Attenzione a non lasciare righe vuote o
            spazi <em>prima</em> di <code>&lt;?php</code>.</li>
            <li>Ricarica questa pagina.</li>
          </ol>
          <p class="nb-small">Il file contiene la password del database: se puoi, imposta i
          permessi a 640.</p>
          <textarea class="nb-code" rows="26" readonly onclick="this.select()"><?= nb_e($result['config']) ?></textarea>
          <p class="nb-small">Percorso completo: <code><?= nb_e($configPath) ?></code></p>
        </div>
        <?php
        return;
    }

    ?>
    <div class="nb-box nb-box--ok">
      <h2>Installazione completata</h2>
      <p>Noblogs è pronto. Restano due cose da fare, in quest'ordine.</p>
    </div>

    <h2>1. Metti in sicurezza l'installazione</h2>
    <ul>
      <li><strong>Cancella la cartella <code>install/</code></strong> dal server. Finché resta lì
      è codice inutile e raggiungibile; l'installer si rifiuta di ripartire, ma la regola è
      togliere quello che non serve.</li>
      <li>Se puoi, fai in modo che la document root del sito punti a <code>public/</code>: è
      l'unica cartella che deve essere raggiungibile dal web. Se l'hosting non lo permette,
      lo <code>.htaccess</code> nella radice del progetto manda già le richieste in
      <code>public/</code> da solo.</li>
      <li>Controlla che <code>config/config.php</code> non sia leggibile dagli altri utenti del
      server (permessi 640).</li>
    </ul>

    <h2>2. Programma la manutenzione</h2>
    <p>Una volta al giorno va eseguita la pulizia: statistiche scadute, iscrizioni mai confermate,
    cache vecchia. Se hai accesso al cron:</p>
    <pre class="nb-code-inline">0 4 * * * php <?= nb_e(NOBLOGS_ROOT) ?>/bin/noblogs manutenzione</pre>
    <p>Se non ce l'hai, la stessa operazione si lancia a mano dalla pagina
    «Quadro generale» dell'amministrazione.</p>

    <p><a class="nb-btn nb-btn--primary" href="/accedi">Vai all'accesso</a></p>
    <?php
}

/**
 * Guscio HTML dell'installer.
 *
 * @param callable():void $body
 */
function nb_install_page(string $title, int $step, callable $body): void
{
    $steps = [
        1 => 'Requisiti',
        2 => 'Database',
        3 => 'Piattaforma',
        4 => 'Amministratore',
        5 => 'Installazione',
    ];
    ?>
<!doctype html>
<html lang="it">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= nb_e($title) ?> — Installazione di Noblogs</title>
<style>
:root {
  --bg: #f5f6f8; --surface: #fff; --text: #1b1e22; --muted: #5f666e;
  --border: #d8dce1; --link: #0b5fbf; --ok: #1c6b3f; --ok-bg: #edf6f0;
  --bad: #b3251c; --bad-bg: #fdf0ef; --warn: #8a5a00; --warn-bg: #fff8e6;
}
@media (prefers-color-scheme: dark) {
  :root {
    --bg: #14171b; --surface: #1c2026; --text: #e6e8ea; --muted: #9aa2ac;
    --border: #2e343c; --link: #6fb0ff; --ok: #78d39c; --ok-bg: #16261d;
    --bad: #ff7a70; --bad-bg: #2b1a19; --warn: #e8b054; --warn-bg: #2a2113;
  }
}
* { box-sizing: border-box; }
body {
  margin: 0; background: var(--bg); color: var(--text);
  font: 16px/1.6 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
}
main { max-width: 46rem; margin: 0 auto; padding: 2rem 1rem 4rem; }
h1 { font-size: 1.6rem; margin: 0 0 0.3rem; }
h2 { font-size: 1.15rem; margin: 1.8rem 0 0.5rem; }
a { color: var(--link); }
code { font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace; font-size: 0.9em;
  background: rgba(127,127,127,0.12); padding: 0.08em 0.3em; border-radius: 3px; }
.nb-lead { color: var(--muted); }
.nb-small { display: block; color: var(--muted); font-size: 0.85rem; line-height: 1.45; }
.nb-indent { margin-inline-start: 1.6rem; }

.nb-steps { display: flex; flex-wrap: wrap; gap: 0.4rem; list-style: none; margin: 0 0 1.5rem; padding: 0;
  font-size: 0.82rem; }
.nb-steps li { padding: 0.2rem 0.6rem; border: 1px solid var(--border); border-radius: 999px; color: var(--muted); }
.nb-steps li.is-current { background: var(--link); border-color: var(--link); color: #fff; }
.nb-steps li.is-done { border-color: var(--ok); color: var(--ok); }

.nb-card { background: var(--surface); border: 1px solid var(--border); border-radius: 8px; padding: 1.4rem 1.5rem; }

label { display: block; margin-bottom: 1rem; font-weight: 600; }
input[type=text], input[type=email], input[type=password], input[type=number], select, textarea {
  display: block; width: 100%; font: inherit; font-weight: 400; color: inherit;
  background: var(--surface); border: 1px solid var(--border); border-radius: 5px;
  padding: 0.45rem 0.6rem; margin-top: 0.25rem;
}
.nb-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 0 1rem; }
@media (max-width: 34rem) { .nb-grid { grid-template-columns: 1fr; } }

.nb-check { font-weight: 400; display: block; margin-bottom: 0.7rem; }
.nb-check input { width: auto; display: inline; margin-inline-end: 0.35rem; }
.nb-fieldset { border: 1px solid var(--border); border-radius: 6px; padding: 0.9rem 1rem; margin: 0 0 1.2rem; }
.nb-fieldset legend { font-weight: 600; padding: 0 0.35rem; }

.nb-btn { display: inline-block; font: inherit; font-size: 0.95rem; padding: 0.45rem 1rem;
  border: 1px solid var(--border); border-radius: 5px; background: var(--surface); color: var(--text);
  text-decoration: none; cursor: pointer; }
.nb-btn--primary { background: var(--link); border-color: var(--link); color: #fff; }

.nb-table { width: 100%; border-collapse: collapse; margin: 1rem 0; }
.nb-table td, .nb-table th { padding: 0.5rem 0.6rem; border-bottom: 1px solid var(--border);
  text-align: start; vertical-align: top; }
.nb-table th { white-space: nowrap; color: var(--muted); font-weight: 600; }
.nb-table__mark { width: 2rem; font-size: 1.1rem; text-align: center; font-weight: 700; }
.nb-table tr.is-ok .nb-table__mark { color: var(--ok); }
.nb-table tr.is-bad .nb-table__mark { color: var(--bad); }
.nb-table tr.is-warn .nb-table__mark { color: var(--warn); }
.nb-table--recap th { width: 12rem; }

.nb-pill { font-size: 0.72rem; border: 1px solid var(--border); border-radius: 999px;
  padding: 0.05rem 0.45rem; color: var(--muted); margin-inline-start: 0.3rem; }
.nb-fix { font-size: 0.85rem; margin-top: 0.35rem; padding: 0.4rem 0.6rem;
  background: var(--warn-bg); color: var(--warn); border-radius: 4px; }

.nb-msg { padding: 0.7rem 0.9rem; border-radius: 5px; border: 1px solid var(--border); }
.nb-msg--error { background: var(--bad-bg); border-color: var(--bad); color: var(--bad); }
.nb-msg--ok { background: var(--ok-bg); border-color: var(--ok); color: var(--ok); }

.nb-box { border: 1px solid var(--border); border-radius: 6px; padding: 0.9rem 1.1rem; margin: 1.2rem 0; }
.nb-box--warn { background: var(--warn-bg); border-color: var(--warn); color: var(--warn); }
.nb-box--ok { background: var(--ok-bg); border-color: var(--ok); color: var(--ok); }
.nb-box h2 { margin-top: 0; }

.nb-code { width: 100%; font-family: ui-monospace, SFMono-Regular, Menlo, Consolas, monospace;
  font-size: 0.8rem; line-height: 1.4; background: var(--surface); color: var(--text);
  border: 1px solid var(--border); border-radius: 5px; padding: 0.6rem; }
.nb-code-inline { background: var(--surface); border: 1px solid var(--border); border-radius: 5px;
  padding: 0.6rem 0.8rem; overflow-x: auto; font-size: 0.85rem; }

footer { max-width: 46rem; margin: 0 auto; padding: 0 1rem 3rem; color: var(--muted); font-size: 0.82rem; }
</style>
</head>
<body>
<main>
  <h1>Installazione di Noblogs</h1>
  <p class="nb-lead"><?= nb_e($title) ?></p>

  <?php if ($step > 0): ?>
    <ol class="nb-steps">
      <?php foreach ($steps as $number => $label): ?>
        <li class="<?= $number === $step ? 'is-current' : ($number < $step ? 'is-done' : '') ?>">
          <?= $number ?>. <?= nb_e($label) ?>
        </li>
      <?php endforeach; ?>
    </ol>
  <?php endif; ?>

  <div class="nb-card">
    <?php $body(); ?>
  </div>
</main>
<footer>
  <p>Noblogs <?= nb_e(NOBLOGS_VERSION) ?> — chiunque raggiunga questo indirizzo può completare
  l'installazione: falla adesso e poi cancella la cartella <code>install/</code>.</p>
</footer>
</body>
</html>
    <?php
}
