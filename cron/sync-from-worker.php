#!/usr/bin/env php
<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

$base = dirname(__DIR__);
$configFile = __DIR__ . '/private/worker-sync.php';
if (!is_file($configFile)) {
    echo "Worker sync désactivé : configuration absente.\n";
    exit(0);
}
$config = require $configFile;
if (!is_array($config) || empty($config['enabled'])) {
    echo "Worker sync désactivé.\n";
    exit(0);
}

$baseUrl = rtrim((string)($config['base_url'] ?? ''), '/');
$token = trim((string)($config['token'] ?? ''));
$timeout = max(2, min(15, (int)($config['timeout_seconds'] ?? 8)));

if (!str_starts_with($baseUrl, 'https://')) {
    throw new RuntimeException('worker-sync.php : base_url HTTPS obligatoire.');
}
if (strlen($token) < 32 || str_starts_with($token, 'REMPLACER_')) {
    throw new RuntimeException('worker-sync.php : token non configuré.');
}

$lock = fopen(__DIR__ . '/private/worker-sync.lock', 'c');
if ($lock === false) {
    throw new RuntimeException('Impossible d’ouvrir le verrou worker-sync.');
}
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    echo "SKIP: synchronisation worker déjà en cours\n";
    exit(0);
}

function rgpd_worker_fetch(string $url, string $token, int $timeout): string
{
    $context = stream_context_create([
        'http' => [
            'method' => 'GET',
            'timeout' => $timeout,
            'follow_location' => 0,
            'ignore_errors' => true,
            'header' => "Authorization: Bearer {$token}\r\nAccept: application/json\r\nUser-Agent: LePotager-RGPD-Sync/2.0\r\n",
        ],
        'ssl' => [
            'verify_peer' => true,
            'verify_peer_name' => true,
        ],
    ]);
    $raw = @file_get_contents($url, false, $context);
    $headers = $http_response_header ?? [];
    $status = 0;
    if (isset($headers[0]) && preg_match('/\s(\d{3})\s/', $headers[0], $m)) {
        $status = (int)$m[1];
    }
    if ($raw === false || $status !== 200) {
        throw new RuntimeException("Worker indisponible ({$status}) : {$url}");
    }
    if (strlen($raw) > 20 * 1024 * 1024) {
        throw new RuntimeException('Réponse worker trop volumineuse.');
    }
    return $raw;
}

function rgpd_atomic_write(string $destination, string $raw): void
{
    $dir = dirname($destination);
    if (!is_dir($dir) || !is_writable($dir)) {
        throw new RuntimeException('Dossier runtime absent ou non inscriptible : ' . $dir);
    }
    $tmp = $destination . '.worker.tmp.' . getmypid();
    if (file_put_contents($tmp, $raw, LOCK_EX) === false || !rename($tmp, $destination)) {
        @unlink($tmp);
        throw new RuntimeException('Écriture atomique impossible : ' . $destination);
    }
}

try {
    $url = $baseUrl . '/data.php?channel=rgpd&file=sources.json';
    $raw = rgpd_worker_fetch($url, $token, $timeout);
    $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);

    if (
        !is_array($data)
        || !isset($data['views'])
        || !is_array($data['views'])
        || !isset($data['our_offers'])
        || !is_array($data['our_offers'])
    ) {
        throw new RuntimeException('Schéma sources.json inattendu.');
    }

    rgpd_atomic_write($base . '/comparateur/data/sources.json', $raw);
} catch (Throwable $e) {
    fwrite(STDERR, "Synchronisation worker annulée, cache RGPD existant conservé : {$e->getMessage()}\n");
    flock($lock, LOCK_UN);
    fclose($lock);
    exit(1);
}

flock($lock, LOCK_UN);
fclose($lock);
echo "OK — cache RGPD synchronisé depuis la lune workers\n";
