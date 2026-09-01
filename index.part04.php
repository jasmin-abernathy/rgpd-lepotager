
            <section class="wizard-step is-active" data-step="1">
                <h3>Votre site fonctionne avec quoi ?</h3>
                <p class="question-help">Pas besoin d'être sûr : « je ne sais pas » est une vraie réponse.</p>
                <div class="choice-grid">
                    <label class="choice"><input type="radio" name="cms" value="WordPress" required><span>WordPress</span></label>
                    <label class="choice"><input type="radio" name="cms" value="Wix"><span>Wix</span></label>
                    <label class="choice"><input type="radio" name="cms" value="Shopify"><span>Shopify</span></label>
                    <label class="choice"><input type="radio" name="cms" value="Autre CMS"><span>Autre CMS / sur mesure</span></label>
                    <label class="choice"><input type="radio" name="cms" value="Pas de site"><span>Je n'ai pas de site</span></label>
                    <label class="choice"><input type="radio" name="cms" value="Je ne sais pas"><span>Je ne sais pas</span></label>
                </div>
            </section>

            <section class="wizard-step" data-step="2">
                <h3>Quels outils ou usages concernent vos clients, membres ou salariés ?</h3>
                <p class="question-help">Cochez tout ce qui s'applique.</p>
                <div class="choice-grid multi">
                    <label class="choice"><input type="checkbox" name="tools[]" value="Formulaire de contact"><span>Formulaire de contact</span></label>
                    <label class="choice"><input type="checkbox" name="tools[]" value="Newsletter"><span>Newsletter</span></label>
                    <label class="choice"><input type="checkbox" name="tools[]" value="Réservation en ligne"><span>Réservation en ligne</span></label>
                    <label class="choice"><input type="checkbox" name="tools[]" value="E-commerce"><span>E-commerce</span></label>
                    <label class="choice"><input type="checkbox" name="tools[]" value="CRM ou fichier client"><span>CRM / fichier client</span></label>
                    <label class="choice"><input type="checkbox" name="tools[]" value="Cloud partagé"><span>Cloud / dossiers partagés</span></label>
                    <label class="choice"><input type="checkbox" name="tools[]" value="Salariés ou prestataires"><span>Salariés / prestataires</span></label>
                    <label class="choice important"><input type="checkbox" name="tools[]" value="Données sensibles"><span>Données sensibles ou très personnelles</span></label>
                </div>
            </section>

            <section class="wizard-step" data-step="3">
                <h3>Et côté organisation ?</h3>
                <div class="question-stack">
                    <fieldset>
                        <legend>Avez-vous un registre des traitements à jour ?</legend>
                        <div class="inline-options">
                            <label><input type="radio" name="register" value="Oui" required> Oui</label>
                            <label><input type="radio" name="register" value="Non"> Non</label>
                            <label><input type="radio" name="register" value="Je ne sais pas"> Je ne sais pas</label>
                        </div>
                    </fieldset>
                    <fieldset>
                        <legend>Avez-vous défini combien de temps conserver les principales données ?</legend>
                        <div class="inline-options">
                            <label><input type="radio" name="retention" value="Oui" required> Oui</label>
                            <label><input type="radio" name="retention" value="Non"> Non</label>
                            <label><input type="radio" name="retention" value="Je ne sais pas"> Je ne sais pas</label>
                        </div>
                    </fieldset>
                    <fieldset>
                        <legend>Savez-vous quels prestataires ou services reçoivent vos données ?</legend>
                        <div class="inline-options">
                            <label><input type="radio" name="processors" value="Oui" required> Oui</label>
                            <label><input type="radio" name="processors" value="Non"> Non</label>
                            <label><input type="radio" name="processors" value="Je ne sais pas"> Je ne sais pas</label>
                        </div>
                    </fieldset>
                </div>
            </section>

            <section class="wizard-step" data-step="4">
                <h3>Et sur le site lui-même ?</h3>
                <div class="question-stack">
                    <fieldset>
                        <legend>Savez-vous quels cookies, traceurs ou services tiers sont chargés ?</legend>
                        <div class="inline-options">
                            <label><input type="radio" name="trackers" value="Oui" required> Oui</label>
                            <label><input type="radio" name="trackers" value="Non"> Non</label>
                            <label><input type="radio" name="trackers" value="Je ne sais pas"> Je ne sais pas</label>
                            <label><input type="radio" name="trackers" value="Pas de site"> Pas de site</label>
                        </div>
                    </fieldset>
                    <fieldset>
                        <legend>Vos formulaires expliquent-ils clairement à quoi servent les données demandées ?</legend>
                        <div class="inline-options">
                            <label><input type="radio" name="forms_info" value="Oui" required> Oui</label>
                            <label><input type="radio" name="forms_info" value="Non"> Non</label>
                            <label><input type="radio" name="forms_info" value="Je ne sais pas"> Je ne sais pas</label>
                            <label><input type="radio" name="forms_info" value="Pas de formulaire"> Pas de formulaire</label>
                        </div>
                    </fieldset>
                </div>
            </section>
