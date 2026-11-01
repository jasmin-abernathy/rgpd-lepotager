
            <section class="wizard-step" data-step="5">
                <div id="resultCard" class="result-card" aria-live="polite"></div>

                <h3 id="finalStepHeading">Recevez une proposition pour votre situation</h3>
                <p class="question-help" id="finalStepHelp">
                    Vos réponses sont reprises automatiquement dans la demande. Il suffit d’indiquer votre adresse e-mail : aucun acompte ni engagement.
                </p>

                <div class="form-grid final-contact-grid">
                    <label class="full" id="contactEmailLabel">Adresse e-mail *
                        <input type="email" name="email" autocomplete="email" required maxlength="180">
                    </label>
                    <div class="full paid-details" id="paidDetails" hidden>
                        <div class="form-grid">
                            <label>Nom *
                                <input type="text" name="name" autocomplete="name" maxlength="120">
                            </label>
                            <label>Structure
                                <input type="text" name="company" autocomplete="organization" maxlength="160">
                            </label>
                            <label>Téléphone
                                <input type="tel" name="phone" autocomplete="tel" maxlength="40">
                            </label>
                            <div class="full billing-fields" aria-labelledby="billing-fields-title">
                        <strong id="billing-fields-title">Facturation · uniquement si vous payez en ligne</strong>
                        <p>Renseignez l’adresse, le code postal et la ville avant de payer : ces champs sont facultatifs pour une demande sans règlement.</p>
                    </div>
                    <label class="full">Adresse de facturation
                        <input type="text" name="billing_address" autocomplete="street-address" maxlength="220" data-payment-required>
                    </label>
                    <label>Code postal
                        <input type="text" name="billing_postcode" autocomplete="postal-code" maxlength="16" data-payment-required>
                    </label>
                    <label>Ville
                        <input type="text" name="billing_city" autocomplete="address-level2" maxlength="120" data-payment-required>
                    </label>
                    <label class="full">SIREN de la structure
                        <input type="text" name="billing_siren" inputmode="numeric" maxlength="14" placeholder="Facultatif si vous êtes un particulier">
                    </label>
                            <label class="full">Un détail utile à ajouter ?
                                <textarea name="message" rows="4" maxlength="2500" placeholder="Facultatif"></textarea>
                            </label>
                        </div>
                    </div>
                </div>

                <p class="privacy-notice" id="freePrivacyNotice">En envoyant votre demande, vous transmettez cette adresse e-mail, vos réponses et la recommandation à Prestadmin et au Potager du Web, uniquement pour préparer une proposition et vous recontacter. <a href="confidentialite.php?ref=<?= e($ref) ?>" target="_blank" rel="noopener">Voir la politique de confidentialité</a>.</p>

                <label class="privacy-check" id="paidPrivacy" hidden>
                    <input type="checkbox" name="privacy_ack" value="1">
                    <span>J'ai lu la <a href="confidentialite.php?ref=<?= e($ref) ?>" target="_blank" rel="noopener">politique de confidentialité</a> et j'ai compris que ma demande sera transmise à <a href="https://www.prestadmin57.fr" target="_blank" rel="noopener noreferrer">Prestadmin</a> et au <a href="https://www.lepotager.org" target="_blank" rel="noopener noreferrer">Potager du Web</a> pour être traitée conjointement.</span>
                </label>

                <div class="diagnostic-payment-note" id="paidNote" hidden>
                    <strong>Diagnostic personnalisé · 150 €</strong>
                    <span>Paiement ponctuel par carte via Stancer. Après confirmation, deux factures distinctes de 75 € sont préparées : une Prestadmin et une Le Potager du Web. Aucun abonnement ni prélèvement récurrent.</span>
                </div>

                <div id="formStatus" class="form-status" role="status" aria-live="polite"></div>
            </section>

            <div class="wizard-actions" id="wizardActions">
                <button class="button button-ghost" id="prevStep" type="button" hidden>Retour</button>
                <button class="button" id="nextStep" type="button">Continuer</button>
                <button class="button" id="submitForm" type="submit" hidden>Envoyer ma demande sans engagement</button>
                <button class="button button-payment" id="payDiagnostic" type="button" hidden>Réserver et payer 150 €</button>
            </div>
        </form>
    </section>
</div>

<script src="assets/app.js?v=7" defer></script>
</body>
</html>
