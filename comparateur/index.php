<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/site-header.php';

function rref(?string $v): string {
    $v = strtolower(trim((string)$v));
    if (str_contains($v, 'prestadmin')) return 'prestadmin';
    if (str_contains($v, 'potager')) return 'potager';
    if (str_contains($v, 'commun') || str_contains($v, 'direct')) return 'commun';
    return '';
}

// Le canal dépend uniquement de l’URL courante : pas de cookie ni de referer.
$ref = '';
foreach (['ref', 'source', 'from', 'canal'] as $k) {
    if (isset($_GET[$k]) && ($candidate = rref((string)$_GET[$k]))) {
        $ref = $candidate;
        break;
    }
}
if (!$ref) {
    $host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
    $ref = str_contains($host, 'prestadmin') ? 'prestadmin' : 'commun';
}
$q = $ref === 'commun' ? '' : '?ref=' . rawurlencode($ref);
$themeLabel = $ref === 'prestadmin' ? 'Prestadmin' : ($ref === 'potager' ? 'Le Potager' : 'Commun');
?>
<!doctype html><html lang="fr"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Comparateur des tarifs RGPD — Prestadmin × Le Potager</title><meta name="description" content="Tarifs publics observés pour des prestations RGPD, avec moyennes, médianes et sources.">
<link rel="stylesheet" href="assets/style.css?v=stable-1746-plus">
<link rel="stylesheet" href="../assets/site-header.css?v=1">
</head>
<body class="theme-<?= htmlspecialchars($ref, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
<?php rgpd_render_header($ref, 'comparateur', false); ?>
<main>
<section class="hero"><div class="shell hero-grid"><div>
<div class="theme-chip" aria-label="Canal affiché"><span class="theme-dot" aria-hidden="true"></span><?= htmlspecialchars($themeLabel, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
<p class="eyebrow" id="hero-zone-label">Observatoire · tarifs publics · France</p><h1>Combien coûtent réellement les prestations RGPD affichées en ligne&nbsp;?</h1>
<p class="lead">Prix publics, groupes comparables, moyenne, médiane, fourchette et registre transparent des sources.</p><div class="badges" aria-label="Accès rapides"><a href="#sources">Sources visibles</a><a href="#releves">Date de relevé</a><a class="dark" href="#methodologie">Sans IA</a></div></div>
<aside><small>Repère actuel</small><strong id="headline">Chargement…</strong><p id="headline-detail">Calcul en cours.</p></aside></div></section>
<section class="shell section"><div class="head"><div><p class="eyebrow">Comparer proprement</p><h2>Moyennes observées</h2></div><p class="muted">Même prestation, même structure cliente, même unité. Une carte n’apparaît qu’à partir de 2 tarifs publics comparables ; une observation seule reste dans les sources.</p></div>
<div class="filters"><label class="region-choice">Zone des tarifs<select id="region"><option value="france">France entière</option><option value="grand-est">Grand Est uniquement</option><option value="moselle">Moselle (57) uniquement</option></select><small>Choix volontaire, sans géolocalisation</small></label><label>Structure<select id="segment"><option value="all">Toutes</option></select></label><label>Prestation<select id="service"><option value="all">Toutes</option></select></label></div><p id="region-note" class="region-note muted"></p><div id="cards" class="cards"></div></section>
<section class="separate-services">
<div class="shell section">
  <div class="head">
    <div>
      <p class="eyebrow">À comparer à part</p>
      <h2>DPO externalisés & expertise juridique</h2>
    </div>
    <p class="muted">Ces prestations sont volontairement exclues des moyennes principales : elles ne correspondent pas au même niveau de mission que notre accompagnement.</p>
  </div>
  <div class="scope-warning">
    <strong>Important : notre prestation n’est pas une consultation juridique.</strong>
    <p id="legal-separation"></p>
  </div>

  <div class="special-block">
    <div class="special-heading">
      <h3>DPO externalisés</h3>
      <p id="dpo-explanation" class="muted"></p>
    </div>
    <div id="dpo-cards" class="cards special-cards"></div>
  </div>

  <div class="special-block legal-block">
    <div class="special-heading">
      <h3>Cabinets d’avocats observés</h3>
      <p class="muted">Repères tarifaires conservés pour transparence, mais jamais intégrés à la moyenne de nos offres.</p>
    </div>
    <div id="legal-cards" class="legal-cards"></div>
  </div>
</div>
</section>
<section class="ours"><div class="shell"><div class="head"><div><p class="eyebrow">Notre offre</p><h2>Prestadmin × Le Potager du Web</h2></div><p>Prix affichés comme repères et exclus de toutes les moyennes.</p></div><div id="offers" class="offers"></div></div></section>
<section class="shell section" id="methodologie"><div class="head"><div><p class="eyebrow">Méthodologie</p><h2>Automatisé, mais pas de l'IA.</h2></div></div>
<div class="method">
<article class="focus method-card">
  <span class="method-pill">Sans IA</span>
  <h3 class="method-title">Pourquoi ce n’est pas de l’IA</h3>
  <p id="notai"></p>
</article>
<article class="method-card">
  <h3 class="method-title">Ce que fait le programme</h3>
  <ol><li>Visite des pages publiques autorisées.</li><li>Extraction selon des règles écrites à l'avance.</li><li>Conservation de la source et de la date.</li><li>Calcul déterministe des statistiques.</li><li>Échec = ancienne valeur conservée, jamais de prix inventé.</li><li>Un crawl hebdomadaire cherche aussi de nouvelles sources ; elles passent obligatoirement par une validation humaine.</li></ol>
</article>
<article class="method-card">
  <h3 class="method-title">Sources et anonymisation</h3>
  <p id="source-privacy">Les profils indépendants et les prestataires issus d’une même plateforme sont regroupés par source et anonymisés dans l’affichage public.</p>
</article>
</div></section>
<section class="sources" id="sources"><div class="shell section"><div class="head"><div><p class="eyebrow">Transparence</p><h2>Sources utilisées</h2></div><p><span id="source-count">0</span> sources regroupant <span id="count">0</span> relevés tarifaires.</p></div>
<p class="muted source-intro">Les indépendants et les profils provenant d’une même plateforme sont anonymisés. Le tableau référence la source utilisée, pas les personnes observées.</p>
<div class="table" id="releves"><table><thead><tr><th>Source</th><th>Nature</th><th>Périmètre relevé</th><th>Relevés</th><th>Statut</th><th>Date</th><th>Lien</th></tr></thead><tbody id="rows"></tbody></table></div></div></section>
</main><footer><div class="shell foot"><div><strong>Prestadmin × Le Potager du Web</strong><br><span>Une offre commune, deux entreprises indépendantes.</span></div><div><a target="_blank" rel="noopener" href="https://www.prestadmin57.fr">Prestadmin ↗</a> · <a target="_blank" rel="noopener" href="https://www.lepotager.org">Le Potager ↗</a></div></div></footer>
<script src="assets/app.js?v=stable-1746-plus" defer></script></body></html>