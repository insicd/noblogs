<?php
/**
 * Punto di ingresso comune: autoloader, configurazione, sessione, errori.
 */

declare(strict_types=1);

define('NOBLOGS_START', microtime(true));
define('NOBLOGS_ROOT', dirname(__DIR__));
define('NOBLOGS_APP', NOBLOGS_ROOT . '/app');
define('NOBLOGS_PUBLIC', NOBLOGS_ROOT . '/public');
define('NOBLOGS_STORAGE', NOBLOGS_ROOT . '/storage');
define('NOBLOGS_VERSION', '1.0.0');

if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    exit('Noblogs richiede PHP 8.1 o superiore. Versione rilevata: ' . PHP_VERSION);
}

// Autoloader PSR-4 minimale: nessun Composer richiesto.
spl_autoload_register(static function (string $class): void {
    $prefix = 'Noblogs\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = NOBLOGS_APP . '/' . str_replace('\\', '/', $relative) . '.php';
    if (is_file($path)) {
        require_once $path;
    }
});

require_once NOBLOGS_APP . '/Support/helpers.php';

use Noblogs\Core\Config;
use Noblogs\Core\ErrorHandler;
use Noblogs\Core\I18n;

Config::load(NOBLOGS_ROOT . '/config/config.php');
ErrorHandler::register(Config::get('debug', false));

date_default_timezone_set('UTC');
mb_internal_encoding('UTF-8');

I18n::load(Config::get('site.locale', 'it'));
