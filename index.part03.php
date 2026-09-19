
            <p class="billing-note">
                <strong>Vous voulez situer ces montants ?</strong> Consultez notre observatoire des tarifs RGPD publics : les moyennes et médianes sont calculées par type de prestation et de structure, avec les sources affichées clairement.
                <a class="text-button" href="<?= e($comparateurHref) ?>">Comparer les tarifs observés →</a>
            </p>

            <p class="billing-note">
                <strong>Facturation :</strong> le diagnostic personnalisé ponctuel à 150 € peut être réglé en ligne par carte via Stancer et est encaissé par <a class="provider-link potager-link" href="https://www.lepotager.org" target="_blank" rel="noopener noreferrer">Le Potager du Web</a>. Pour les packs, <a class="provider-link admin-link" href="https://www.prestadmin57.fr" target="_blank" rel="noopener noreferrer">Prestadmin</a> et Le Potager restent deux entreprises indépendantes et émettent chacun leur propre devis/facture pour leur partie. Les suivis annuels ne sont pas proposés au paiement dans ce parcours.
            </p>
        </div>
    </section>

    <section class="section wp-section ptm-corrected">
        <div class="shell wp-grid">
            <div class="ptm-copy">
                <p class="eyebrow">WordPress · SPIP · Grav · capacité actuelle</p>
                <h2>Privacy Tracker Manager se décline sur plusieurs CMS.<br><em>Le niveau de maturité n'est pas le même partout.</em></h2>
                <p>
                    Privacy Tracker Manager (PTM) est développé par
                    <a class="provider-link potager-link-dark" href="https://www.lepotager.org" target="_blank" rel="noopener noreferrer">Le Potager du Web</a>.
                    La version WordPress est celle utilisée dans les prestations actuelles. Le portage SPIP, encore en développement, sait déjà inventorier les plugins actifs et lancer un scan progressif du HTML rendu, mais doit encore être validé sur une copie exécutable avant production. Le portage Grav dispose d'une première base locale, d'un inventaire de plugins et des règles PTM ; son scanner n'est pas encore actif et il reste lui aussi réservé au développement.
                </p>

                <div class="ptm-status">
                    <div class="ptm-status-ok"><b>✓ Aujourd’hui</b><span>WordPress : utilisation dans le back-office lors des contrôles prévus. SPIP : inventaire local et scan progressif en développement. Grav : première baseline locale et inventaire disponibles.</span></div>
                    <div class="ptm-status-no"><b>× Pas encore</b><span>Pas de validation production des portages SPIP et Grav, pas de surveillance automatique 24/7 et pas de remontée automatique vers un tableau de bord externe sur aucune version.</span></div>
                </div>

                <p class="ptm-human"><strong>Les formules annuelles rémunèrent donc des revues et interventions humaines planifiées.</strong> Les portages SPIP et Grav ne sont pas présentés comme des solutions de production tant que leurs validations respectives ne sont pas terminées.</p>
            </div>

            <div class="ptm-card ptm-screen" aria-label="Schéma de fonctionnement actuel de Privacy Tracker Manager sur WordPress">
                <div class="ptm-screen-top"><span></span><span></span><span></span><small>WordPress du client</small></div>
                <div class="ptm-screen-body">
                    <div class="ptm-sidebar"><span>Tableau de bord</span><span>Pages</span><span>Extensions</span><b>Privacy Tracker Manager</b></div>
                    <div class="ptm-panel">
                        <small>REVUE PLANIFIÉE</small><strong>Privacy Tracker Manager</strong>
                        <i></i><i class="short"></i>
                        <div class="ptm-local">Contrôle depuis l’administration WordPress</div>
                        <div class="ptm-remote">× Aucune liaison distante automatique dans la version actuelle</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="section disclaimer-section">
        <div class="shell disclaimer">
            <strong>Ce que nous faisons — et ce que nous ne prétendons pas faire.</strong>
            <p>
                L'accompagnement vise à organiser et mettre en œuvre une démarche de conformité dans votre activité et vos outils.
                Il ne constitue pas une certification de conformité ni une consultation juridique.
                Lorsqu'une situation exige une analyse juridique spécialisée, elle doit être traitée par un professionnel compétent.
            </p>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="shell footer-grid">
        <div>
            <strong><a href="https://www.prestadmin57.fr" target="_blank" rel="noopener noreferrer">Prestadmin</a> × <a href="https://www.lepotager.org" target="_blank" rel="noopener noreferrer">Le Potager du Web</a></strong>
            <p>Une offre commune, deux entreprises indépendantes.</p>
        </div>
        <div class="footer-links">
            <a href="https://www.prestadmin57.fr" target="_blank" rel="noopener noreferrer">Prestadmin ↗</a>
            <a href="https://www.lepotager.org" target="_blank" rel="noopener noreferrer">Le Potager ↗</a>
            <a href="<?= e($comparateurHref) ?>">Comparateur des tarifs</a>
            <a href="mentions-legales.php?ref=<?= e($ref) ?>">Mentions légales</a>
            <a href="confidentialite.php?ref=<?= e($ref) ?>">Confidentialité</a>
        </div>
    </div>
</footer>

<div class="modal" id="diagnosticModal" hidden>
    <div class="modal-backdrop js-close-diagnostic" aria-hidden="true"></div>
    <section class="wizard" role="dialog" aria-modal="true" aria-labelledby="wizardTitle">
        <button class="wizard-close js-close-diagnostic" type="button" aria-label="Fermer">×</button>

        <div class="wizard-top">
            <div>
                <p class="eyebrow">Le Jardinier RGPD</p>
                <h2 id="wizardTitle">Voyons ce qui mérite d'être vérifié.</h2>
            </div>
            <p class="wizard-counter" aria-live="polite"><span id="stepNumber">1</span>/5</p>
        </div>

        <div class="progress" aria-hidden="true"><span id="progressBar"></span></div>

        <form id="diagnosticForm" action="contact.php" method="post" novalidate>
            <input type="hidden" name="source" value="<?= e($ref) ?>">
            <input type="hidden" name="recommendation" id="recommendationField" value="">
            <input type="hidden" name="summary" id="summaryField" value="">
            <input type="hidden" name="started_at" id="startedAt" value="">
            <div class="hp-field" aria-hidden="true">
                <label>Votre site web <input type="text" name="website" tabindex="-1" autocomplete="off"></label>
            </div>
