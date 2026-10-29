<?php
declare(strict_types=1);
$rawRef = strtolower((string)($_GET['ref'] ?? 'direct'));
$ref = in_array($rawRef, ['prestadmin','potager','direct'], true) ? $rawRef : 'direct';
function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
?>
<!doctype html>
<html lang="fr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Politique de confidentialité — Prestadmin × Le Potager</title>
  <link rel="canonical" href="https://rgpd.lepotager.org/confidentialite.php">
  <link rel="stylesheet" href="assets/style.css?v=stable-20260820">
  <link rel="stylesheet" href="assets/themes.css?v=stable-20260820">
  <link rel="stylesheet" href="assets/root-serif-patch.css?v=stable-20260820">
<link rel="stylesheet" href="/assets/accessibility.css?v=2">
<script defer src="/assets/accessibility.js?v=2"></script>
</head>
<body class="theme-<?= e((string)$ref) ?>" data-ref="<?= e((string)$ref) ?>">
<main class="legal-page">
  <div class="shell legal-wrap">
    <a class="back-link" href="./?ref=<?= e((string)$ref) ?>">← Retour à l'offre</a>
    <p class="eyebrow">Données personnelles</p>
    <h1>Politique de confidentialité</h1>

    <h2>Qui reçoit la demande ?</h2>
    <p>
      Le formulaire est utilisé pour une offre commune entre <strong><a href="https://www.prestadmin57.fr" target="_blank" rel="noopener noreferrer">Prestadmin ↗</a></strong>
      et <strong><a href="https://www.lepotager.org" target="_blank" rel="noopener noreferrer">Le Potager du Web ↗</a></strong>. Les informations envoyées sont transmises
      aux deux entités afin qu'elles puissent analyser la demande et préparer, lorsque cela est pertinent,
      leurs prestations respectives.
    </p>

    <h2>Données traitées</h2>
    <p>
      Le parcours peut transmettre : le CMS ou type de site, les outils sélectionnés, quelques réponses
      d'orientation sur l'organisation et le site, l'orientation indicative calculée, le nom, la structure,
      l'adresse e-mail, le téléphone lorsqu'il est fourni et le message libre.
      Une indication d'origine commerciale (« Prestadmin », « Potager » ou « accès direct ») accompagne également
      le message afin d'identifier le canal qui a amené la demande.
      En cas de réservation payante, l'adresse de facturation, le code postal, la ville et, le cas échéant,
      le SIREN sont également recueillis. Un identifiant de commande et des références de paiement
      sont conservés pour vérifier le règlement et établir les factures. Les données de carte sont saisies
      sur la page de paiement du prestataire Stancer, et non dans ce formulaire.
    </p>

    <h2>Finalité</h2>
    <p>
      Ces informations servent à répondre à la demande, préparer un diagnostic ou une proposition commerciale,
      répartir l'intervention entre les deux entités et assurer le suivi de l'échange.
    </p>

    <h2>Base et durée</h2>
    <p>
      Le traitement est réalisé pour répondre à une démarche initiée par la personne et préparer, le cas échéant,
      une relation contractuelle. Une demande gratuite est transmise par e-mail sans enregistrement de son contenu
      dans une base de données du site. Pour une réservation payante, les informations nécessaires à la commande
      et à sa vérification sont aussi conservées dans des fichiers protégés hors de l'espace public de l'hébergement.
      Les commandes non payées sont normalement supprimées de ce stockage après sept jours ; les commandes payées
      et factures sont conservées selon les obligations comptables et légales applicables.
      Les échanges reçus par e-mail sont conservés au maximum deux ans à compter du dernier échange lorsqu'ils ne
      débouchent pas sur une relation contractuelle, sauf nécessité particulière ou obligation légale.
    </p>

    <h2>Destinataires et hébergement</h2>
    <p>
      Les destinataires sont Prestadmin et Le Potager du Web, dans la limite de leurs besoins respectifs.
      Le site et sa messagerie sont hébergés chez o2switch, qui peut traiter des journaux techniques
      nécessaires à la sécurité et au fonctionnement de ses services.
      En cas de paiement par carte, Stancer intervient comme prestataire de paiement et traite les données
      nécessaires à la transaction selon sa propre politique de confidentialité.
      Les deux factures de 75 € sont établies après confirmation du paiement.
    </p>

    <h2>Cookies et traceurs</h2>
    <p>
      Cette V1 n'intègre aucun outil publicitaire ni outil d'analyse tiers et n'utilise aucun cookie pour attribuer
      l'origine commerciale. L'information <code>?ref=prestadmin</code> ou <code>?ref=potager</code> reste simplement
      dans l'adresse de la page et est jointe au message lors de l'envoi du formulaire.
    </p>

    <h2>Vos droits</h2>
    <p>
      Pour demander l'accès, la rectification, l'effacement ou la limitation des données vous concernant,
      ou exercer un droit d'opposition lorsqu'il est applicable, vous pouvez écrire à
      <a href="mailto:contact@lepotager.org">contact@lepotager.org</a>.
      Vous pouvez également introduire une réclamation auprès de la CNIL.
    </p>

    <h2>Précaution</h2>
    <p>
      N'envoyez pas de mots de passe, coordonnées bancaires, pièces administratives ou données sensibles dans le
      champ libre. Si de tels éléments sont nécessaires à une mission, un canal adapté sera convenu après le premier échange.
    </p>

    <p><small>Version mise à jour le 29 octobre 2026. À réexaminer lors de toute évolution du paiement, du formulaire ou de l'hébergement.</small></p>
  </div>
</main>
</body>
</html>
