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
  <title>Mentions légales — Prestadmin × Le Potager</title>
  <link rel="canonical" href="https://rgpd.lepotager.org/mentions-legales.php">
  <link rel="stylesheet" href="assets/style.css?v=stable-20260820">
  <link rel="stylesheet" href="assets/themes.css?v=stable-20260820">
  <link rel="stylesheet" href="assets/root-serif-patch.css?v=stable-20260820">
<link rel="stylesheet" href="/assets/accessibility.css?v=1">
<script defer src="/assets/accessibility.js?v=1"></script>
</head>
<body class="theme-<?= e((string)$ref) ?>" data-ref="<?= e((string)$ref) ?>">
<main class="legal-page">
  <div class="shell legal-wrap">
    <a class="back-link" href="./?ref=<?= e((string)$ref) ?>">← Retour à l'offre</a>
    <p class="eyebrow">Informations légales</p>
    <h1>Mentions légales</h1>

    <h2>Objet du site</h2>
    <p>
      Ce mini-site présente une offre commune de Prestadmin et du Potager du Web.
      Les deux activités restent juridiquement et commercialement indépendantes et facturent chacune leurs propres prestations.
    </p>

    <h2><a href="https://www.lepotager.org" target="_blank" rel="noopener noreferrer">Le Potager du Web ↗</a></h2>
    <ul>
      <li>Nom commercial : Le Potager du Web</li>
      <li>Entrepreneur : Jasmin Lévêque</li>
      <li>Forme juridique : entrepreneur individuel</li>
      <li>SIREN : 808 087 399</li>
      <li>SIRET : 808 087 399 00032</li>
      <li>Adresse professionnelle : 3 rue Brunehaut, 57000 Metz, France</li>
      <li>Courriel : contact@lepotager.org</li>
      <li>Site : <a href="https://www.lepotager.org">lepotager.org</a></li>
    </ul>

    <h2><a href="https://www.prestadmin57.fr" target="_blank" rel="noopener noreferrer">Prestadmin ↗</a></h2>
    <ul>
      <li>Nom commercial : Prestadmin</li>
      <li>Entrepreneuse : Camille Poirel</li>
      <li>Forme juridique : entreprise individuelle</li>
      <li>SIREN : 944 326 917</li>
      <li>Adresse professionnelle : 3 rue Brunehaut, 57000 Metz, France</li>
      <li>Courriel de contact du site : contact@prestadmin57.fr</li>
      <li>Site : <a href="https://prestadmin57.fr">prestadmin57.fr</a></li>
    </ul>

    <h2>Responsables de publication</h2>
    <p>Jasmin Lévêque pour Le Potager du Web et Camille Poirel pour Prestadmin.</p>

    <h2>Hébergement</h2>
    <p>
      o2switch — Chemin des Pardiaux, 63000 Clermont-Ferrand, France — 04 44 44 60 40 —
      <a href="https://www.o2switch.fr">o2switch.fr</a>.
    </p>

    <h2>Portée de l'accompagnement</h2>
    <p>
      Les informations publiées présentent une prestation d'accompagnement opérationnel.
      Elles ne constituent ni une consultation juridique, ni une certification de conformité.
      Une situation nécessitant une expertise juridique spécialisée doit être orientée vers un professionnel compétent.
    </p>

    <h2>Propriété intellectuelle</h2>
    <p>
      Sauf mention contraire, les textes, éléments graphiques et développements propres à ce mini-site restent protégés
      par les règles applicables. Les composants open source éventuels conservent leurs licences respectives.
    </p>

    <p><small>Version préparée le 17 août 2026.</small></p>
  </div>
</main>
</body>
</html>
