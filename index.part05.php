
            <section class="wizard-step" data-step="5">
                <div id="resultCard" class="result-card" aria-live="polite"></div>

                <h3>Vous voulez qu'on regarde cela avec vous ?</h3>
                <p class="question-help">
                    Les réponses ci-dessus seront jointes à votre demande. Elles ne sont pas enregistrées dans une base de données du site.
                </p>

                <div class="form-grid">
                    <label>Nom *
                        <input type="text" name="name" autocomplete="name" required maxlength="120">
                    </label>
                    <label>Structure
                        <input type="text" name="company" autocomplete="organization" maxlength="160">
                    </label>
                    <label>Adresse e-mail *
                        <input type="email" name="email" autocomplete="email" required maxlength="180">
                    </label>
                    <label>Téléphone
                        <input type="tel" name="phone" autocomplete="tel" maxlength="40">
                    </label>
                    <label class="full">Un détail utile à ajouter ?
                        <textarea name="message" rows="4" maxlength="2500" placeholder="Facultatif"></textarea>
                    </label>
                </div>

                <label class="privacy-check">
                    <input type="checkbox" name="privacy_ack" value="1" required>
                    <span>J'ai lu la <a href="confidentialite.php?ref=<?= e($ref) ?>" target="_blank" rel="noopener">politique de confidentialité</a> et j'ai compris que ma demande sera transmise à <a href="https://www.prestadmin57.fr" target="_blank" rel="noopener noreferrer">Prestadmin</a> et au <a href="https://www.lepotager.org" target="_blank" rel="noopener noreferrer">Potager du Web</a> pour être traitée conjointement.</span>
                </label>

                <div id="formStatus" class="form-status" role="status" aria-live="polite"></div>
            </section>

            <div class="wizard-actions">
                <button class="button button-ghost" id="prevStep" type="button" hidden>Retour</button>
                <button class="button" id="nextStep" type="button">Continuer</button>
                <button class="button" id="submitForm" type="submit" hidden>Envoyer ma demande</button>
            </div>
        </form>
    </section>
</div>

<script src="assets/app.js?v=2" defer></script>
</body>
</html>
