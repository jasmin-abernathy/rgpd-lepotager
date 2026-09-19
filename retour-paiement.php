<?php
declare(strict_types=1);

define('RGPD_PAYMENT_BOOTSTRAP', true);
require_once __DIR__ . '/includes/payment-runtime.php';
$siteConfig = require __DIR__ . '/config.php';

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');

$id = trim((string)($_GET['d'] ?? ''));
$state = preg_replace('/[^a-f0-9]/', '', strtolower((string)($_GET['state'] ?? ''))) ?? '';
$record = rgpd_payment_valid_id($id) ? rgpd_payment_read($id) : [];
$details = ['kind' => 'error', 'label' => 'Référence invalide'];

if ($record !== [] && $state !== '' && hash_equals((string)($record['return_state'] ?? ''), $state) && !empty($record['payment_intent_id'])) {
    try {
        $intent = rgpd_payment_request('GET', '/payment_intents/' . rawurlencode((string)$record['payment_intent_id']));
        $payment = null;
        $paymentId = (string)($intent['payment'] ?? '');
        if (preg_match('/^paym_[A-Za-z0-9]{24}$/', $paymentId)) {
            $payment = rgpd_payment_request('GET', '/payments/' . rawurlencode($paymentId));
        }
        $details = rgpd_payment_status($intent, $payment);

        if ($details['kind'] === 'success') {
            $record['status'] = 'paid';
            $record['paid_at'] = $record['paid_at'] ?? time();
            $record['payment_id'] = $paymentId;
            if (empty($record['notification_attempted_at'])) {
                rgpd_payment_notify($siteConfig, $id, $record);
                $record['notification_attempted_at'] = time();
            }
            rgpd_payment_write($id, $record);
        } elseif ($details['kind'] === 'error') {
            $record['status'] = 'payment_failed';
            rgpd_payment_write($id, $record);
        }
    } catch (Throwable $e) {
        error_log('[RGPD retour paiement] ' . $e->getMessage());
        $details = ['kind' => 'pending', 'label' => 'Vérification en cours'];
    }
}

$title = $details['kind'] === 'success'
    ? 'Diagnostic réservé'
    : ($details['kind'] === 'pending' ? 'Paiement en cours de vérification' : 'Paiement non confirmé');

$message = $details['kind'] === 'success'
    ? 'Le paiement de 150 € est confirmé. Votre diagnostic personnalisé est enregistré et les informations du questionnaire ont été transmises pour traitement.'
    : ($details['kind'] === 'pending'
        ? 'Stancer ou votre banque finalise peut-être encore l’opération. Évitez de relancer immédiatement un second paiement.'
        : 'Aucun paiement confirmé n’est associé à ce retour.');
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title><?=htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?> — RGPD au propre</title>
  <link rel="stylesheet" href="assets/style.css?v=3">
</head>
<body>
<main class="payment-page">
  <div class="shell">
    <section class="payment-card">
      <span class="payment-state <?=$details['kind']==='error'?'error':($details['kind']==='pending'?'pending':'')?>"><?=htmlspecialchars((string)$details['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?></span>
      <h1><?=htmlspecialchars($title, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?>.</h1>
      <p><?=htmlspecialchars($message, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?></p>
      <?php if ($record !== []): ?>
      <div class="payment-meta">
        <p>Référence : <strong><?=htmlspecialchars($id, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')?></strong></p>
        <p>Prestation : <strong>Diagnostic personnalisé RGPD</strong></p>
        <p>Montant : <strong>150,00 €</strong></p>
      </div>
      <?php endif; ?>
      <p><a class="button" href="./">Retour à RGPD au propre</a></p>
    </section>
  </div>
</main>
</body>
</html>
