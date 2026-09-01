<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function respond(int $status, array $payload): never {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(405, ['ok' => false, 'message' => 'Méthode non autorisée.']);
}

// Petit contrôle d'origine : suffisant pour un formulaire sans action sensible.
$origin = (string)($_SERVER['HTTP_ORIGIN'] ?? '');
$allowedOrigin = rtrim((string)$config['site_url'], '/');
if ($origin !== '' && rtrim($origin, '/') !== $allowedOrigin) {
    respond(403, ['ok' => false, 'message' => 'Origine non autorisée.']);
}

// Honeypot
if (trim((string)($_POST['website'] ?? '')) !== '') {
    respond(200, ['ok' => true]);
}

// Temps minimal de remplissage
$startedAt = (int)($_POST['started_at'] ?? 0);
$elapsedMs = (int)(microtime(true) * 1000) - $startedAt;
if ($startedAt <= 0 || $elapsedMs < 2500) {
    respond(429, ['ok' => false, 'message' => 'Envoi trop rapide. Merci de réessayer.']);
}

// Limitation simple par IP hachée, dans le dossier temporaire de PHP.
$ip = (string)($_SERVER['REMOTE_ADDR'] ?? 'unknown');
$key = hash('sha256', $ip);
$rateFile = rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'rgpd_form_' . $key . '.json';
$now = time();
$windowStart = $now - 3600;
$events = [];

if (is_file($rateFile)) {
    $decoded = json_decode((string)@file_get_contents($rateFile), true);
    if (is_array($decoded)) {
        $events = array_values(array_filter($decoded, static fn($t) => is_int($t) && $t >= $windowStart));
    }
}

if (count($events) >= (int)$config['max_submissions_per_hour']) {
    respond(429, ['ok' => false, 'message' => 'Trop de demandes depuis cette connexion. Réessayez plus tard.']);
}

$events[] = $now;
@file_put_contents($rateFile, json_encode($events), LOCK_EX);

function clean(string $value, int $max = 2500): string {
    $value = trim(strip_tags($value));
    $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u', '', $value) ?? '';
    if (function_exists('mb_substr')) {
        return mb_substr($value, 0, $max);
    }
    return substr($value, 0, $max);
}

$name = clean((string)($_POST['name'] ?? ''), 120);
$company = clean((string)($_POST['company'] ?? ''), 160);
$email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
$phone = clean((string)($_POST['phone'] ?? ''), 40);
$message = clean((string)($_POST['message'] ?? ''), 2500);
$summary = clean((string)($_POST['summary'] ?? ''), 4500);
$recommendation = clean((string)($_POST['recommendation'] ?? ''), 120);
$source = strtolower(clean((string)($_POST['source'] ?? 'direct'), 30));
$privacyAck = (string)($_POST['privacy_ack'] ?? '') === '1';

$allowedSources = ['prestadmin', 'potager', 'direct'];
if (!in_array($source, $allowedSources, true)) {
    $source = 'direct';
}

if ($name === '' || !$email || !$privacyAck) {
    respond(422, ['ok' => false, 'message' => 'Merci de renseigner le nom, une adresse e-mail valide et de confirmer la lecture de la politique de confidentialité.']);
}

$sourceLabel = match ($source) {
    'prestadmin' => 'PRESTADMIN',
    'potager' => 'POTAGER',
    default => 'DIRECT / COMMUN',
};

$primary = match ($source) {
    'prestadmin' => $config['prestadmin_email'],
    'potager' => $config['potager_email'],
    default => $config['potager_email'],
};

$secondary = match ($source) {
    'prestadmin' => $config['potager_email'],
    'potager' => $config['prestadmin_email'],
    default => $config['prestadmin_email'],
};

$subject = '[RGPD][' . $sourceLabel . '] ' . ($company !== '' ? $company : $name) . ' — ' . ($recommendation !== '' ? $recommendation : 'nouvelle demande');

$body = "Nouvelle demande depuis rgpd.lepotager.org\n\n";
$body .= "ORIGINE COMMERCIALE : {$sourceLabel}\n";
$body .= "INTERLOCUTEUR PRINCIPAL : " . ($source === 'prestadmin' ? 'Prestadmin' : ($source === 'potager' ? 'Le Potager du Web' : 'Commun')) . "\n";
$body .= "RECOMMANDATION DU PARCOURS : " . ($recommendation !== '' ? $recommendation : 'non calculée') . "\n\n";
$body .= "CONTACT\n";
$body .= "Nom : {$name}\n";
$body .= "Structure : " . ($company !== '' ? $company : '—') . "\n";
$body .= "E-mail : {$email}\n";
$body .= "Téléphone : " . ($phone !== '' ? $phone : '—') . "\n\n";
$body .= "RÉSUMÉ DU DIAGNOSTIC\n{$summary}\n\n";
$body .= "MESSAGE LIBRE\n" . ($message !== '' ? $message : '—') . "\n\n";
$body .= "Le formulaire n'enregistre pas cette demande dans une base de données du site.\n";

$headers = [
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
    'From: ' . $config['from_name'] . ' <' . $config['from_email'] . '>',
    'Reply-To: ' . $name . ' <' . $email . '>',
    'Cc: ' . $secondary,
    'X-Origin-Commerciale: ' . $sourceLabel,
];

$sent = @mail(
    $primary,
    '=?UTF-8?B?' . base64_encode($subject) . '?=',
    $body,
    implode("\r\n", $headers)
);

if (!$sent) {
    respond(500, [
        'ok' => false,
        'message' => 'Le serveur n’a pas pu envoyer le message. Vous pouvez écrire directement à contact@lepotager.org.'
    ]);
}

respond(200, ['ok' => true, 'message' => 'Demande transmise.']);
