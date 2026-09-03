<?php
declare(strict_types=1);

/**
 * Header public partagé par l'accueil et le comparateur.
 *
 * Les variantes ne changent que l'identité de marque et les couleurs :
 * - direct / commun : Prestadmin × Le Potager du Web ;
 * - potager : Le Potager du Web ;
 * - prestadmin : Prestadmin.
 */

function rgpd_header_ref(string $ref): string
{
    return match (strtolower(trim($ref))) {
        'prestadmin' => 'prestadmin',
        'potager' => 'potager',
        'commun', 'direct' => 'direct',
        default => 'direct',
    };
}

function rgpd_header_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function rgpd_header_url(string $path, string $ref, string $fragment = ''): string
{
    $ref = rgpd_header_ref($ref);
    $url = $path;

    if ($ref !== 'direct') {
        $url .= (str_contains($path, '?') ? '&' : '?') . 'ref=' . rawurlencode($ref);
    }

    if ($fragment !== '') {
        $url .= '#' . rawurlencode($fragment);
    }

    return $url;
}

function rgpd_render_header(string $ref = 'direct', string $active = 'home', bool $diagnosticIsLocal = false): void
{
    $ref = rgpd_header_ref($ref);
    $brand = $ref === 'direct' ? 'common' : $ref;

    $homeHref = rgpd_header_url('/', $ref);
    $methodHref = rgpd_header_url('/', $ref, 'methode');
    $pricingHref = rgpd_header_url('/', $ref, 'offres');
    $compareHref = rgpd_header_url('/comparateur/', $ref);
    $diagnosticHref = rgpd_header_url('/', $ref, 'diagnostic');
    ?>
<header class="rgpd-global-header" data-brand="<?= rgpd_header_e($brand) ?>">
    <div class="rgpd-global-identity">
        <div class="shell rgpd-global-identity-row">
            <a class="rgpd-global-home" href="<?= rgpd_header_e($homeHref) ?>" aria-label="Accueil RGPD au propre">
                <span class="rgpd-global-mark" aria-hidden="true">
                    <img src="/assets/ptm-logo.svg" alt="Logo de RGPD au propre" width="92" height="92">
                </span>
                <span class="rgpd-global-title-wrap">
                    <strong>RGPD au propre</strong>
                    <small>organisation × numérique</small>
                </span>
            </a>
        </div>
    </div>

    <div class="rgpd-global-brandbar">
        <div class="shell rgpd-global-brandrow">
            <div class="rgpd-global-brandcopy">
                <?php if ($ref === 'prestadmin'): ?>
                    <strong class="rgpd-global-brandname rgpd-global-brandname-prestadmin">PRESTADMIN</strong>
                    <span class="rgpd-global-tagline">ASSISTANCE &amp; EFFICACITÉ</span>
                <?php elseif ($ref === 'potager'): ?>
                    <strong class="rgpd-global-brandname rgpd-global-brandname-potager">Le Potager du Web</strong>
                    <span class="rgpd-global-tagline">numérique responsable · open source</span>
                <?php else: ?>
                    <strong class="rgpd-global-brandname rgpd-global-brandname-common">
                        <span class="rgpd-global-common-prestadmin">PRESTADMIN</span>
                        <span class="rgpd-global-common-times" aria-hidden="true">×</span>
                        <span class="rgpd-global-common-potager">Le Potager du Web</span>
                    </strong>
                    <span class="rgpd-global-tagline">Un seul parcours · deux métiers complémentaires</span>
                <?php endif; ?>
            </div>

            <?php if ($diagnosticIsLocal): ?>
                <button class="rgpd-global-diagnostic js-open-diagnostic" type="button">
                    <span class="rgpd-global-diagnostic-bolt" aria-hidden="true">⌁</span>
                    <span>Diagnostic rapide</span>
                    <span class="rgpd-global-diagnostic-arrow" aria-hidden="true">↘</span>
                </button>
            <?php else: ?>
                <a class="rgpd-global-diagnostic" href="<?= rgpd_header_e($diagnosticHref) ?>">
                    <span class="rgpd-global-diagnostic-bolt" aria-hidden="true">⌁</span>
                    <span>Diagnostic rapide</span>
                    <span class="rgpd-global-diagnostic-arrow" aria-hidden="true">↘</span>
                </a>
            <?php endif; ?>
        </div>
    </div>

    <div class="rgpd-global-subnav">
        <div class="shell">
            <nav class="rgpd-global-nav" aria-label="Navigation principale">
                <a<?= $active === 'home' ? ' class="active" aria-current="page"' : '' ?> href="<?= rgpd_header_e($homeHref) ?>">Accueil</a>
                <a href="<?= rgpd_header_e($methodHref) ?>">Méthode</a>
                <a href="<?= rgpd_header_e($pricingHref) ?>">Tarifs</a>
                <a<?= $active === 'comparateur' ? ' class="active" aria-current="page"' : '' ?> href="<?= rgpd_header_e($compareHref) ?>">Comparateur</a>
                <button class="rgpd-global-a11y" type="button" data-rgpd-accessibility-toggle aria-pressed="false">Version accessible</button>
            </nav>
        </div>
    </div>
</header>
<?php
}
