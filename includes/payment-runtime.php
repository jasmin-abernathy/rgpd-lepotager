<?php
declare(strict_types=1);

if (!defined('RGPD_PAYMENT_BOOTSTRAP')) {
    http_response_code(404);
    exit;
}

const RGPD_STANCER_CONFIG = '/home/sc1leja3715/stancer-private/private/stancer-config.php';
const RGPD_PAYMENT_STORAGE = '/home/sc1leja3715/stancer-private/private/rgpd-payments';
const RGPD_PAYMENT_PRICE_CENTS = 15000;
const RGPD_PAYMENT_SERVICE = 'Diagnostic personnalisé RGPD';
const RGPD_PAYMENT_RETURN_URL = 'https://rgpd.lepotager.org/retour-paiement.php';
const RGPD_PAYMENT_RETENTION_SECONDS = 604800;

function rgpd_payment_config(): array
{
    static $config = null;
    if (is_array($config)) return $config;
    if (!is_readable(RGPD_STANCER_CONFIG)) throw new RuntimeException('Configuration de paiement indisponible.');
    $raw = require RGPD_STANCER_CONFIG;
    if (!is_array($raw)) throw new RuntimeException('Configuration de paiement invalide.');
    $key = (string)($raw['stancer_secret_key'] ?? $raw['api_key'] ?? '');
    $api = (string)($raw['stancer_api_base'] ?? $raw['api_base'] ?? 'https://api.stancer.com/v2');
    if (!preg_match('/^s(?:test|prod)_[A-Za-z0-9]+$/', $key)) throw new RuntimeException('Configuration Stancer invalide.');
    if (parse_url($api, PHP_URL_SCHEME) !== 'https' || parse_url($api, PHP_URL_HOST) !== 'api.stancer.com') {
        throw new RuntimeException('Adresse API Stancer invalide.');
    }
    return $config = ['key' => $key, 'api' => rtrim($api, '/')];
}

function rgpd_payment_request(string $method, string $path, ?array $payload = null): array
{
    $config = rgpd_payment_config();
    $ch = curl_init($config['api'] . '/' . ltrim($path, '/'));
    if ($ch === false) throw new RuntimeException('Connexion Stancer impossible.');
    $headers = ['Accept: application/json', 'User-Agent: RGPD-LePotager/1.0', 'Expect:'];
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 15,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_USERPWD => $config['key'] . ':',
        CURLOPT_HTTPAUTH => CURLAUTH_BASIC,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    ];
    if (strtoupper($method) !== 'GET') $opts[CURLOPT_CUSTOMREQUEST] = strtoupper($method);
    if ($payload !== null) {
        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $opts[CURLOPT_POSTFIELDS] = $body;
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Content-Length: ' . strlen($body);
    }
    $opts[CURLOPT_HTTPHEADER] = $headers;
    curl_setopt_array($ch, $opts);
    $body = curl_exec($ch);
    $errno = curl_errno($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if ($body === false || $errno !== 0) throw new RuntimeException('Stancer est momentanément indisponible.');
    $data = $body !== '' ? json_decode($body, true, 64, JSON_THROW_ON_ERROR) : [];
    if ($status < 200 || $status >= 300 || !is_array($data)) throw new RuntimeException('Stancer a refusé la demande.');
    return $data;
}

function rgpd_payment_storage_ready(): void
{
    if (!is_dir(RGPD_PAYMENT_STORAGE) && !mkdir(RGPD_PAYMENT_STORAGE, 0700, true) && !is_dir(RGPD_PAYMENT_STORAGE)) {
        throw new RuntimeException('Stockage privé indisponible.');
    }
    @chmod(RGPD_PAYMENT_STORAGE, 0700);
    if (!is_writable(RGPD_PAYMENT_STORAGE)) throw new RuntimeException('Stockage privé non inscriptible.');
}

function rgpd_payment_valid_id(string $id): bool
{
    return (bool)preg_match('/^RGPD-[0-9]{8}-[a-f0-9]{20}$/', $id);
}

function rgpd_payment_record_path(string $id): string
{
    if (!rgpd_payment_valid_id($id)) throw new InvalidArgumentException('Référence invalide.');
    return RGPD_PAYMENT_STORAGE . '/' . $id . '.json';
}

function rgpd_payment_read(string $id): array
{
    $path = rgpd_payment_record_path($id);
    if (!is_readable($path)) return [];
    $data = json_decode((string)file_get_contents($path), true);
    return is_array($data) ? $data : [];
}

function rgpd_payment_write(string $id, array $record): void
{
    rgpd_payment_storage_ready();
    $path = rgpd_payment_record_path($id);
    $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
    $json = json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    if (file_put_contents($tmp, $json, LOCK_EX) === false) throw new RuntimeException('Écriture impossible.');
    @chmod($tmp, 0600);
    if (!rename($tmp, $path)) {
        @unlink($tmp);
        throw new RuntimeException('Finalisation impossible.');
    }
    @chmod($path, 0600);
}

function rgpd_payment_cleanup(): void
{
    rgpd_payment_storage_ready();
    $limit = time() - RGPD_PAYMENT_RETENTION_SECONDS;
    foreach (glob(RGPD_PAYMENT_STORAGE . '/RGPD-*.json') ?: [] as $path) {
        if (!is_file($path) || filemtime($path) >= $limit) continue;
        $data = json_decode((string)@file_get_contents($path), true);
        if (is_array($data) && ($data['status'] ?? '') === 'paid') continue;
        @unlink($path);
    }
}

function rgpd_payment_same_origin(): bool
{
    foreach (['HTTP_ORIGIN', 'HTTP_REFERER'] as $key) {
        $value = (string)($_SERVER[$key] ?? '');
        if ($value === '') continue;
        $host = (string)(parse_url($value, PHP_URL_HOST) ?? '');
        if ($host !== '' && strcasecmp($host, 'rgpd.lepotager.org') !== 0) return false;
    }
    return true;
}

function rgpd_payment_status(array $intent, ?array $payment): array
{
    $status = strtolower((string)($payment['status'] ?? $intent['status'] ?? 'unknown'));
    if (in_array($status, ['captured','paid','succeeded'], true)) return ['kind' => 'success', 'label' => 'Paiement confirmé'];
    if (in_array($status, ['to_capture','capture_sent','authorized','processing','pending','requires_capture','require_payment_method'], true)) {
        return ['kind' => 'pending', 'label' => 'Paiement en cours'];
    }
    if (in_array($status, ['failed','refused','expired','cancelled','canceled','disputed'], true)) return ['kind' => 'error', 'label' => 'Paiement non abouti'];
    return ['kind' => 'pending', 'label' => 'Vérification en cours'];
}

function rgpd_payment_clean(string $value, int $max): string
{
    $value = trim(strip_tags($value));
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    return function_exists('mb_substr') ? mb_substr($value, 0, $max) : substr($value, 0, $max);
}

function rgpd_payment_notify(array $siteConfig, string $id, array $record): void
{
    if (!empty($record['notification_attempted_at'])) return;
    $source = (string)($record['source'] ?? 'direct');
    $primary = $source === 'prestadmin' ? $siteConfig['prestadmin_email'] : $siteConfig['potager_email'];
    $secondary = $source === 'prestadmin' ? $siteConfig['potager_email'] : $siteConfig['prestadmin_email'];
    $name = (string)($record['name'] ?? '');
    $email = (string)($record['email'] ?? '');
    $company = (string)($record['company'] ?? '');
    $subject = '[RGPD][PAYÉ] ' . ($company !== '' ? $company : $name) . ' — Diagnostic personnalisé 150 €';
    $body = "Diagnostic personnalisé RGPD payé.\n\n"
        . "Référence : {$id}\n"
        . "Nom : {$name}\n"
        . "Structure : " . ($company !== '' ? $company : '—') . "\n"
        . "E-mail : {$email}\n"
        . "Téléphone : " . ((string)($record['phone'] ?? '') ?: '—') . "\n"
        . "Origine : " . strtoupper($source) . "\n"
        . "Montant réglé : 150,00 €\n"
        . "Ventilation : 75,00 € Le Potager du Web + 75,00 € Prestadmin\n"
        . (!empty($record['invoices']['potager']['number']) ? "Facture Potager : " . (string)$record['invoices']['potager']['number'] . "\n" : "Facture Potager : en attente\n")
        . (!empty($record['invoices']['prestadmin']['number']) ? "Facture Prestadmin : " . (string)$record['invoices']['prestadmin']['number'] . "\n\n" : "Facture Prestadmin : en attente\n\n")
        . "Résumé du diagnostic\n" . (string)($record['summary'] ?? '') . "\n\n"
        . "Message\n" . ((string)($record['message'] ?? '') ?: '—') . "\n";
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'From: ' . $siteConfig['from_name'] . ' <' . $siteConfig['from_email'] . '>',
        'Reply-To: ' . $name . ' <' . $email . '>',
        'Cc: ' . $secondary,
    ];
    if (!@mail($primary, '=?UTF-8?B?' . base64_encode($subject) . '?=', $body, implode("\r\n", $headers))) {
        error_log('[RGPD paiement] Notification non envoyée pour ' . $id);
    }
}
