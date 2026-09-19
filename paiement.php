<?php
declare(strict_types=1);

define('RGPD_PAYMENT_BOOTSTRAP', true);
require_once __DIR__ . '/includes/payment-runtime.php';
$siteConfig = require __DIR__ . '/config.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');

function payment_fail(string $message, int $status = 400): never
{
    http_response_code($status);
    ?><!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="robots" content="noindex,nofollow"><title>Paiement indisponible — RGPD au propre</title><link rel="stylesheet" href="assets/style.css?v=4"></head><body><main class="payment-page"><div class="shell"><section class="payment-card"><span class="payment-state error">Paiement non lancé</span><h1>Le paiement n’a pas démarré.</h1><p><?=htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?></p><p><a class="button" href="./#diagnostic">Retour au diagnostic</a></p></section></div></main></body></html><?php
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') payment_fail('Méthode non autorisée.', 405);
if (!rgpd_payment_same_origin()) payment_fail('Origine du formulaire invalide.', 403);

try {
    rgpd_payment_cleanup();

    if ((string)($_POST['service'] ?? '') !== 'diagnostic-personnalise') throw new InvalidArgumentException('Prestation non disponible au paiement.');
    if (trim((string)($_POST['website'] ?? '')) !== '') payment_fail('La demande n’a pas pu être validée.');
    $startedAt = (int)($_POST['started_at'] ?? 0);
    if ($startedAt <= 0 || ((int)(microtime(true) * 1000) - $startedAt) < 2500) throw new InvalidArgumentException('Formulaire complété trop rapidement.');

    $name = rgpd_payment_clean((string)($_POST['name'] ?? ''), 120);
    $company = rgpd_payment_clean((string)($_POST['company'] ?? ''), 160);
    $email = filter_var(trim((string)($_POST['email'] ?? '')), FILTER_VALIDATE_EMAIL);
    $phone = rgpd_payment_clean((string)($_POST['phone'] ?? ''), 40);
    $billingAddress = rgpd_payment_clean((string)($_POST['billing_address'] ?? ''), 220);
    $billingPostcode = rgpd_payment_clean((string)($_POST['billing_postcode'] ?? ''), 16);
    $billingCity = rgpd_payment_clean((string)($_POST['billing_city'] ?? ''), 120);
    $billingSiren = preg_replace('/\\s+/', '', rgpd_payment_clean((string)($_POST['billing_siren'] ?? ''), 14)) ?? '';
    $message = rgpd_payment_clean((string)($_POST['message'] ?? ''), 2500);
    $summary = rgpd_payment_clean((string)($_POST['summary'] ?? ''), 4500);
    $recommendation = rgpd_payment_clean((string)($_POST['recommendation'] ?? ''), 120);
    $source = strtolower(rgpd_payment_clean((string)($_POST['source'] ?? 'direct'), 30));
    $privacy = (string)($_POST['privacy_ack'] ?? '') === '1';

    if ($name === '' || !$email || !$privacy) throw new InvalidArgumentException('Nom, e-mail et accord de confidentialité requis.');
    if ($billingAddress === '' || $billingPostcode === '' || $billingCity === '') throw new InvalidArgumentException('Adresse de facturation requise pour le paiement.');
    if ($billingSiren !== '' && !preg_match('/^\\d{9}$/', $billingSiren)) throw new InvalidArgumentException('SIREN client invalide.');
    if (strlen($summary) < 20) throw new InvalidArgumentException('Le diagnostic doit être complété avant le paiement.');
    if (!in_array($source, ['prestadmin','potager','direct'], true)) $source = 'direct';

    $id = 'RGPD-' . gmdate('Ymd') . '-' . bin2hex(random_bytes(10));
    $state = bin2hex(random_bytes(24));
    $returnUrl = RGPD_PAYMENT_RETURN_URL . '?d=' . rawurlencode($id) . '&state=' . rawurlencode($state);

    $intent = rgpd_payment_request('POST', '/payment_intents/', [
        'amount' => RGPD_PAYMENT_PRICE_CENTS,
        'currency' => 'eur',
        'description' => 'Diagnostic personnalise RGPD — Le Potager du Web',
        'order_id' => $id,
        'methods_allowed' => ['card'],
        'threeds' => 'required',
        'capture' => true,
        'return_url' => $returnUrl,
        'metadata' => ['service' => 'diagnostic-rgpd', 'dossier' => $id],
    ]);

    $intentId = (string)($intent['id'] ?? '');
    $url = (string)($intent['url'] ?? '');
    if (!preg_match('/^pi_[A-Za-z0-9]{24}$/', $intentId) || !filter_var($url, FILTER_VALIDATE_URL) || parse_url($url, PHP_URL_SCHEME) !== 'https' || parse_url($url, PHP_URL_HOST) !== 'payment.stancer.com') {
        throw new RuntimeException('Réponse de paiement invalide.');
    }

    $record = [
        'id' => $id,
        'created_at' => time(),
        'status' => 'payment_started',
        'amount_cents' => RGPD_PAYMENT_PRICE_CENTS,
        'service' => RGPD_PAYMENT_SERVICE,
        'name' => $name,
        'company' => $company,
        'email' => (string)$email,
        'phone' => $phone,
        'billing_address' => $billingAddress,
        'billing_postcode' => $billingPostcode,
        'billing_city' => $billingCity,
        'billing_siren' => $billingSiren,
        'message' => $message,
        'summary' => $summary,
        'recommendation' => $recommendation,
        'source' => $source,
        'payment_intent_id' => $intentId,
        'payment_url' => $url,
        'return_state' => $state,
    ];
    rgpd_payment_write($id, $record);

    header('Location: ' . $url, true, 303);
    exit;
} catch (Throwable $e) {
    error_log('[RGPD paiement] ' . $e->getMessage());
    payment_fail('La réservation n’a pas pu être préparée. Aucun paiement n’a été demandé. Vous pouvez réessayer depuis le diagnostic.');
}
