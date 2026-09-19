<?php
declare(strict_types=1);

$config = require __DIR__ . '/config.php';
require_once __DIR__ . '/includes/site-header.php';

$allowedRefs = ['prestadmin', 'potager', 'direct'];
$ref = strtolower((string)($_GET['ref'] ?? 'direct'));
if (!in_array($ref, $allowedRefs, true)) {
    $ref = 'direct';
}

$origin = [
    'prestadmin' => [
        'label' => 'Prestadmin',
        'person' => 'Camille',
        'note' => 'Camille reste votre interlocutrice commerciale principale pour cette demande.',
    ],
    'potager' => [
        'label' => 'Le Potager du Web',
        'person' => 'Jasmin',
        'note' => 'Jasmin reste votre interlocuteur commercial principal pour cette demande.',
    ],
    'direct' => [
        'label' => 'accès direct',
        'person' => 'l’équipe',
        'note' => 'Votre demande sera lue par les deux partenaires puis répartie selon le besoin.',
    ],
][$ref];

$comparateurHref = 'comparateur/' . ($ref === 'direct' ? '' : '?ref=' . rawurlencode($ref));

function e(string $value): string {
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

header('X-Frame-Options: SAMEORIGIN');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Permissions-Policy: geolocation=(), microphone=(), camera=(), payment=()");
?>
<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>RGPD au propre — Prestadmin × Le Potager du Web</title>
    <meta name="description" content="Un diagnostic court pour identifier ce qui mérite d'être vérifié côté organisation, documents, site web, formulaires et traceurs. Offre commune Prestadmin × Le Potager du Web.">
    <link rel="canonical" href="https://rgpd.lepotager.org/">
    <meta name="theme-color" content="#274b3a">
    <link rel="icon" href="/favicon.svg" type="image/svg+xml">
    <link rel="stylesheet" href="assets/style.css?v=4">
    <link rel="stylesheet" href="assets/themes.css?v=21">
    <link rel="stylesheet" href="assets/theme-contrast-fix.css?v=1">
    <link rel="stylesheet" href="assets/site-header.css?v=1">
<link rel="stylesheet" href="/assets/accessibility.css?v=1">
<script defer src="/assets/accessibility.js?v=1"></script>
</head>
<body class="theme-<?= e($ref) ?>" data-ref="<?= e($ref) ?>">
<a class="skip-link" href="#main">Aller au contenu</a>

<?php rgpd_render_header($ref, 'home', true); ?>

<main id="main">
    <section class="hero">
        <div class="shell hero-grid">
            <div>
                <div class="ref-theme-badge" aria-label="Version affichée">
                    <span class="ref-theme-dot" aria-hidden="true"></span>
                    <?php if ($ref === 'prestadmin'): ?>
                        Version Prestadmin
                    <?php elseif ($ref === 'potager'): ?>
                        Version Le Potager
                    <?php else: ?>
                        Version commune
                    <?php endif; ?>
                </div>
                <p class="eyebrow">RGPD · site web · organisation</p>
                <h1>Mettre vos données au propre, sans transformer votre activité en usine à procédures.</h1>
                <p class="lead">
                    <a class="provider-link admin-link" href="https://www.prestadmin57.fr" target="_blank" rel="noopener noreferrer">Prestadmin</a> remet de l’ordre dans les documents, procédures et échéances.
                    <a class="provider-link potager-link" href="https://www.lepotager.org" target="_blank" rel="noopener noreferrer">Le Potager du Web</a> vérifie ce qui se passe réellement côté site, formulaires et outils numériques.
                    Privacy Tracker Manager (PTM) est utilisé sur WordPress <strong>lors des revues planifiées depuis l’administration du site</strong>. Des portages SPIP et Grav sont aussi en développement : SPIP dispose déjà d’un scan progressif et d’un inventaire local à valider sur copie exécutable ; Grav dispose d’une première base locale et doit encore implémenter puis valider le scan du HTML rendu avant tout usage en production. Aucune de ces versions ne remonte aujourd’hui automatiquement ses résultats vers un tableau de bord externe.
                </p>
                <div class="hero-actions">
                    <a class="button button-ghost" href="#offres">Voir les tarifs</a>
                </div>
                <p class="microcopy">Le diagnostic rapide est accessible directement depuis le header · environ 3 minutes · aucune donnée envoyée avant votre validation finale.</p>
            </div>

            <aside class="origin-card dual-card" aria-label="Répartition de l'accompagnement">
                <?php if ($ref === 'prestadmin'): ?>
                    <a class="pill origin-provider-pill" href="https://www.prestadmin57.fr" target="_blank" rel="noopener noreferrer">Entrée : Prestadmin ↗</a>
                <?php elseif ($ref === 'potager'): ?>
                    <a class="pill origin-provider-pill" href="https://www.lepotager.org" target="_blank" rel="noopener noreferrer">Entrée : Le Potager du Web ↗</a>
                <?php else: ?>
                    <span class="pill">Entrée : accès commun</span>
                <?php endif; ?>
                <h2>Un seul parcours.<br>Deux métiers.</h2>
                <p><?= e($origin['note']) ?></p>
                <div class="dual-role dual-role-admin">
                    <a href="https://www.prestadmin57.fr" target="_blank" rel="noopener noreferrer">Prestadmin ↗</a>
                    <span>organisation · registre · procédures · suivi documentaire</span>
                </div>