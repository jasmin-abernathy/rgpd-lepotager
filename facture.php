<?php
declare(strict_types=1);

define('RGPD_PAYMENT_BOOTSTRAP', true);
require_once __DIR__ . '/includes/payment-runtime.php';
require_once __DIR__ . '/includes/invoice-runtime.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');

$id = trim((string)($_GET['d'] ?? ''));
$state = preg_replace('/[^a-f0-9]/', '', strtolower((string)($_GET['state'] ?? ''))) ?? '';
$issuer = strtolower(trim((string)($_GET['issuer'] ?? '')));

if (!rgpd_payment_valid_id($id) || !in_array($issuer, ['potager', 'prestadmin'], true)) {
    http_response_code(404);
    exit('Facture introuvable.');
}

$record = rgpd_payment_read($id);
if (
    $record === []
    || ($record['status'] ?? '') !== 'paid'
    || $state === ''
    || !hash_equals((string)($record['return_state'] ?? ''), $state)
) {
    http_response_code(403);
    exit('Accès refusé.');
}

$path = rgpd_invoice_file_path($record, $issuer);
$number = (string)($record['invoices'][$issuer]['number'] ?? '');
if ($path === null || $number === '') {
    http_response_code(404);
    exit('Facture non disponible.');
}

header('Content-Type: application/pdf');
header('Content-Length: ' . filesize($path));
header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '-', $number) . '.pdf"');
readfile($path);
