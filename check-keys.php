<?php
// Script temporaneo di verifica delle chiavi di traduzione. Da cancellare.
declare(strict_types=1);

$root = __DIR__;

$catalogue = [];
foreach (glob($root . '/lang/it/*.php') ?: [] as $file) {
    $catalogue += require $file;
}
$english = [];
foreach (glob($root . '/lang/en/*.php') ?: [] as $file) {
    $english += require $file;
}

// Solo i file di questo lavoro: gli altri hanno cataloghi propri, scritti
// altrove e non ancora completi.
$used = [];
$files = array_merge(
    glob($root . '/app/Controllers/Admin/*.php') ?: [],
    glob($root . '/app/Views/admin/*.php') ?: [],
    [$root . '/app/Views/layouts/admin.php', $root . '/bin/noblogs', $root . '/install/index.php']
);

foreach ($files as $file) {
    if (!is_file($file)) {
        continue;
    }
    $source = (string) file_get_contents($file);
    if (preg_match_all('/__\(\s*([\'"])([^\'"]+)\1/', $source, $m)) {
        foreach ($m[2] as $key) {
            $used[$key][] = str_replace($root . '/', '', $file);
        }
    }
    // Chiavi costruite dinamicamente: vanno verificate a mano.
    if (preg_match_all('/__\(\s*[^\'")]*\$/', $source, $dyn)) {
        echo "DINAMICA in " . str_replace($root . '/', '', $file) . "\n";
    }
}

$missing = 0;
foreach ($used as $key => $where) {
    if (!array_key_exists($key, $catalogue)) {
        echo "MANCANTE (it): $key   ← " . implode(', ', array_unique($where)) . "\n";
        $missing++;
    }
}

// Solo le chiavi admin.* devono esistere anche in inglese: gli altri cataloghi
// inglesi non sono compito di questo lavoro.
foreach (array_keys($catalogue) as $key) {
    if (str_starts_with($key, 'admin.') && !array_key_exists($key, $english)) {
        echo "MANCANTE (en): $key\n";
        $missing++;
    }
}
foreach (array_keys($english) as $key) {
    if (!array_key_exists($key, $catalogue)) {
        echo "IN PIU' (en, assente in it): $key\n";
        $missing++;
    }
}

$unusedAdmin = [];
foreach (array_keys($catalogue) as $key) {
    if (str_starts_with($key, 'admin.') && !isset($used[$key])) {
        $unusedAdmin[] = $key;
    }
}
if ($unusedAdmin !== []) {
    echo "\nChiavi admin.* mai usate (" . count($unusedAdmin) . "):\n  " . implode("\n  ", $unusedAdmin) . "\n";
}

echo "\nChiavi usate: " . count($used) . " — problemi: $missing\n";
exit($missing > 0 ? 1 : 0);
