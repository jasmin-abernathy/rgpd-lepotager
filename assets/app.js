
(() => {
  const modal = document.getElementById('diagnosticModal');
  const form = document.getElementById('diagnosticForm');
  if (!modal || !form) return;

  const steps = [...form.querySelectorAll('.wizard-step')];
  const openButtons = [...document.querySelectorAll('.js-open-diagnostic')];
  const closeButtons = [...document.querySelectorAll('.js-close-diagnostic')];
  const next = document.getElementById('nextStep');
  const prev = document.getElementById('prevStep');
  const submit = document.getElementById('submitForm');
  const payDiagnostic = document.getElementById('payDiagnostic');
  const wizardActions = document.getElementById('wizardActions');
  const journeyModeField = document.getElementById('journeyMode');
  const journeyHint = document.getElementById('journeyHint');
  const wizardCounter = document.getElementById('wizardCounter');
  const wizardTitle = document.getElementById('wizardTitle');
  const switchJourney = document.getElementById('switchJourney');
  const finalStepHeading = document.getElementById('finalStepHeading');
  const finalStepHelp = document.getElementById('finalStepHelp');
  const stepNumber = document.getElementById('stepNumber');
  const progressBar = document.getElementById('progressBar');
  const resultCard = document.getElementById('resultCard');
  const recommendationField = document.getElementById('recommendationField');
  const summaryField = document.getElementById('summaryField');
  const status = document.getElementById('formStatus');
  const startedAt = document.getElementById('startedAt');

  let current = 0;
  let lastFocused = null;
  let journeyMode = 'guide';
  let paymentRedirecting = false;

  function resetPaymentActions() {
    paymentRedirecting = false;
    if (wizardActions) wizardActions.hidden = false;
    if (switchJourney) switchJourney.hidden = false;
    if (payDiagnostic) {
      payDiagnostic.disabled = false;
      payDiagnostic.removeAttribute('aria-busy');
    }
  }

  function setJourney(mode) {
    journeyMode = mode === 'direct' ? 'direct' : 'guide';
    journeyModeField.value = journeyMode;
    resetPaymentActions();
    status.className = 'form-status';
    status.textContent = '';
    const direct = journeyMode === 'direct';
    wizardTitle.textContent = direct ? 'Réserver le diagnostic personnalisé à 150 €.' : 'Trouvons la formule qui correspond à votre besoin.';
    journeyHint.textContent = direct
      ? 'Sans questionnaire : renseignez vos coordonnées et votre facturation, puis payez en sécurité. Aucun abonnement.'
      : 'Conseil gratuit : quatre étapes de questions, puis une demande sans paiement. Le diagnostic à 150 € reste facultatif.';
    switchJourney.textContent = direct
      ? 'Je préfère être conseillé gratuitement sur mon forfait →'
      : 'Je préfère réserver directement le diagnostic à 150 € →';
    wizardCounter.hidden = direct;
    finalStepHeading.textContent = direct ? 'Vos coordonnées pour réserver' : 'Et maintenant, quelle suite souhaitez-vous ?';
    finalStepHelp.textContent = direct
      ? 'Ce diagnostic est payant (150 € en une fois). Le questionnaire de conseil n’est pas nécessaire pour le réserver.'
      : 'Envoyez une demande gratuite pour échanger sur le forfait conseillé, ou réservez en option le diagnostic personnalisé à 150 €.';
    form.querySelectorAll('[data-payment-required]').forEach(field => {
      field.required = direct;
    });
    showStep(direct ? steps.length - 1 : 0);
  }

  const focusableSelector = [
    'a[href]',
    'button:not([disabled])',
    'input:not([disabled]):not([type="hidden"])',
    'select:not([disabled])',
    'textarea:not([disabled])',
    '[tabindex]:not([tabindex="-1"])'
  ].join(',');

  const labels = {
    cms: 'CMS / site',
    tools: 'Outils / usages',
    register: 'Registre',
    retention: 'Durées de conservation',
    processors: 'Prestataires / destinataires',
    trackers: 'Traceurs connus',
    forms_info: 'Information sous formulaires'
  };

  function openModal(mode = 'guide') {
    lastFocused = document.activeElement;
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    startedAt.value = String(Date.now());
    setJourney(mode);
    setTimeout(() => modal.querySelector('.wizard-close')?.focus(), 30);
  }

  function closeModal() {
    modal.hidden = true;
    document.body.style.overflow = '';
    if (lastFocused) lastFocused.focus();
  }

  openButtons.forEach(btn => btn.addEventListener('click', () => openModal(btn.dataset.journey || 'guide')));
  switchJourney?.addEventListener('click', () => setJourney(journeyMode === 'direct' ? 'guide' : 'direct'));
  closeButtons.forEach(btn => btn.addEventListener('click', closeModal));

  // Permet au bouton du comparateur de revenir sur la racine et d’ouvrir directement le diagnostic.
  if (window.location.hash === '#diagnostic') {
    openModal('guide');
  }

  window.addEventListener('pageshow', () => {
    if (paymentRedirecting) {
      resetPaymentActions();
      status.className = 'form-status';
      status.textContent = '';
    }
  });

  document.addEventListener('keydown', e => {
    if (modal.hidden) return;
    if (e.key === 'Escape') {
      closeModal();
      return;
    }
    if (e.key !== 'Tab') return;

    const dialog = modal.querySelector('[role="dialog"]');
    if (!dialog) return;
    const focusable = [...dialog.querySelectorAll(focusableSelector)].filter(element =>
      !element.hidden && element.getAttribute('aria-hidden') !== 'true' && element.getClientRects().length > 0
    );
    if (!focusable.length) return;

    const first = focusable[0];
    const last = focusable[focusable.length - 1];
    if (e.shiftKey && document.activeElement === first) {
      e.preventDefault();
      last.focus();
    } else if (!e.shiftKey && document.activeElement === last) {
      e.preventDefault();
      first.focus();
    } else if (!dialog.contains(document.activeElement)) {
      e.preventDefault();
      first.focus();
    }
  });

  function showStep(index) {
    current = Math.max(0, Math.min(index, steps.length - 1));
    steps.forEach((step, i) => step.classList.toggle('is-active', i === current));
    stepNumber.textContent = String(current + 1);
    progressBar.style.width = `${((current + 1) / steps.length) * 100}%`;
    const direct = journeyMode === 'direct';
    const finalStep = current === steps.length - 1;
    prev.hidden = direct || current === 0;
    next.hidden = direct || finalStep;
    submit.hidden = direct || !finalStep;
    if (payDiagnostic) payDiagnostic.hidden = !finalStep;
    if (wizardActions) wizardActions.classList.toggle('is-final', finalStep);

    if (current === steps.length - 1) {
      buildResult();
    }

    const heading = steps[current].querySelector('h3');
    if (heading) {
      heading.setAttribute('tabindex', '-1');
      heading.focus({preventScroll:true});
    }
  }

  function checkedValue(name) {
    return form.querySelector(`[name="${name}"]:checked`)?.value || '';
  }

  function checkedValues(name) {
    return [...form.querySelectorAll(`[name="${name}[]"]:checked`)].map(i => i.value);
  }

  function validateCurrentStep() {
    const required = [...steps[current].querySelectorAll('[required]')];
    const groupNames = [...new Set(required.map(el => el.name))];

    for (const name of groupNames) {
      const els = [...steps[current].querySelectorAll(`[name="${CSS.escape(name)}"]`)];
      const ok = els.some(el => {
        if (el.type === 'radio' || el.type === 'checkbox') return el.checked;
        return el.value.trim() !== '';
      });
      if (!ok) {
        els[0]?.focus();
        els[0]?.reportValidity();
        return false;
      }
    }
    return true;
  }

  function calculateRecommendation() {
    const cms = checkedValue('cms');
    const tools = checkedValues('tools');
    let score = 0;

    score += Math.min(tools.length, 5);
    if (tools.includes('Données sensibles')) score += 3;
    if (tools.includes('Salariés ou prestataires')) score += 1;
    if (tools.includes('Newsletter')) score += 1;
    if (tools.includes('E-commerce')) score += 1;
    if (tools.includes('Réservation en ligne')) score += 1;

    ['register','retention','processors','trackers','forms_info'].forEach(name => {
      const value = checkedValue(name);
      if (value === 'Non') score += 2;
      if (value === 'Je ne sais pas') score += 1;
    });

    if (cms === 'Je ne sais pas' || cms === 'Pas de site') {
      return {
        title: 'Commencer par le diagnostic personnalisé',
        price: '150 €',
        code: 'Diagnostic personnalisé 150 €',
        text: 'Votre situation mérite surtout d’être clarifiée avant de choisir un forfait. Le diagnostic personnalisé permet de vérifier ce qui est réellement applicable.'
      };
    }

    if (score >= 9) {
      return {
        title: 'Le Pack Complet semble le plus probable',
        price: '990 €',
        code: 'Pack Complet 990 €',
        text: cms === 'WordPress'
          ? 'Plusieurs flux ou points d’organisation semblent se cumuler. Le Pack Complet permet de traiter la partie documentaire, les prestataires et l’audit technique. Sur WordPress, Dendrila Privacy peut être installé dans le site et utilisé lors des revues planifiées ; il ne fournit pas encore de surveillance distante automatique.'
          : 'Plusieurs flux ou points d’organisation semblent se cumuler. Le Pack Complet permet une revue plus approfondie. Dendrila Privacy n’est pas inclus car il est réservé à WordPress.'
      };
    }

    return {
      title: 'Le Pack Essentiel semble être le bon point de départ',
      price: '690 €',
      code: 'Pack Essentiel 690 €',
      text: cms === 'WordPress'
        ? 'Votre situation paraît compatible avec une remise au propre ciblée. Sur WordPress, Dendrila Privacy peut être installé et configuré dans le site pour être consulté lors des revues planifiées ; il ne remonte pas encore les informations à distance.'
        : 'Votre situation paraît compatible avec une remise au propre ciblée. La partie technique sera traitée sans Dendrila Privacy si le site n’est pas sous WordPress.'
    };
  }

  function buildSummary() {
    const parts = [];
    parts.push(`${labels.cms}: ${checkedValue('cms') || 'non renseigné'}`);
    const tools = checkedValues('tools');
    parts.push(`${labels.tools}: ${tools.length ? tools.join(', ') : 'aucun sélectionné'}`);
    ['register','retention','processors','trackers','forms_info'].forEach(name => {
      parts.push(`${labels[name]}: ${checkedValue(name) || 'non renseigné'}`);
    });
    return parts.join('\n');
  }

  function buildResult() {
    if (journeyMode === 'direct') {
      resultCard.innerHTML = `
        <p class="eyebrow">Réservation directe · sans questionnaire</p>
        <h3>Diagnostic personnalisé RGPD</h3>
        <p class="result-price">150 € · paiement unique</p>
        <p>Vous réservez un état des lieux avec Prestadmin et Le Potager du Web, sans devoir choisir un forfait maintenant.</p>
        <small>Le paiement ouvre une réservation, pas une certification de conformité.</small>
      `;
      recommendationField.value = 'Diagnostic personnalisé 150 €';
      summaryField.value = 'Réservation directe du diagnostic personnalisé RGPD à 150 € ; questionnaire non rempli.';
      return;
    }
    const r = calculateRecommendation();
    resultCard.innerHTML = `
      <p class="eyebrow">Orientation indicative</p>
      <h3>${escapeHtml(r.title)}</h3>
      <p class="result-price">${escapeHtml(r.price)}</p>
      <p>${escapeHtml(r.text)}</p>
      <small>Cette orientation n'est pas une conclusion de conformité : elle sert seulement à préparer l'échange.</small>
    `;
    recommendationField.value = r.code;
    summaryField.value = buildSummary();
  }

  function escapeHtml(value) {
    return String(value).replace(/[&<>"']/g, c => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#039;'
    }[c]));
  }

  next.addEventListener('click', () => {
    if (journeyMode !== 'guide' || current === steps.length - 1) return;
    if (!validateCurrentStep()) return;
    showStep(current + 1);
  });

  prev.addEventListener('click', () => {
    if (journeyMode === 'guide' && current > 0) showStep(current - 1);
  });

  function paymentError(message, field = null) {
    status.className = 'form-status error';
    status.textContent = message;
    if (field) {
      field.focus();
      field.reportValidity?.();
    }
    return false;
  }

  function validateFinalContact() {
    status.className = 'form-status';
    status.textContent = '';
    if (!validateCurrentStep()) {
      return paymentError('Pour continuer vers Stancer, renseignez votre nom, votre e-mail et acceptez la politique de confidentialité.');
    }
    const email = form.querySelector('[name="email"]');
    const privacy = form.querySelector('[name="privacy_ack"]');
    if (email && !email.checkValidity()) {
      return paymentError('Vérifiez votre adresse e-mail avant de payer.', email);
    }
    if (privacy && !privacy.checked) {
      return paymentError('Acceptez la politique de confidentialité avant de payer.', privacy);
    }
    for (const field of form.querySelectorAll('[data-payment-required]')) {
      if (!String(field.value || '').trim()) {
        field.setCustomValidity('Ce champ est nécessaire pour établir les factures.');
        field.addEventListener('input', () => field.setCustomValidity(''), {once:true});
        return paymentError('Complétez votre adresse de facturation (adresse, code postal et ville) avant de payer.', field);
      }
    }
    const siren = form.querySelector('[name="billing_siren"]');
    if (siren && siren.value.trim()) {
      const normalized = siren.value.replace(/\s+/g, '');
      if (!/^\d{9}$/.test(normalized)) {
        siren.setCustomValidity('Le SIREN doit contenir 9 chiffres.');
        siren.addEventListener('input', () => siren.setCustomValidity(''), {once:true});
        return paymentError('Le SIREN doit contenir neuf chiffres, ou rester vide pour un particulier.', siren);
      }
      siren.value = normalized;
    }
    if (!startedAt.value) {
      return paymentError('Le questionnaire a perdu son état. Fermez-le puis rouvrez-le avant de payer.');
    }
    return true;
  }

  if (payDiagnostic) {
    payDiagnostic.addEventListener('click', () => {
      if (paymentRedirecting || current !== steps.length - 1) return;
      if (!validateFinalContact()) return;
      try {
        buildResult();
        status.className = 'form-status';
        status.textContent = 'Redirection vers le paiement sécurisé Stancer…';
        paymentRedirecting = true;
        payDiagnostic.disabled = true;
        payDiagnostic.setAttribute('aria-busy', 'true');
        if (wizardActions) wizardActions.hidden = true;
        if (switchJourney) switchJourney.hidden = true;

        const paymentForm = document.createElement('form');
        paymentForm.method = 'post';
        paymentForm.action = 'paiement.php';
        paymentForm.hidden = true;

        const data = new FormData(form);
        data.set('service', 'diagnostic-personnalise');
        data.set('journey_mode', journeyMode);
        for (const [name, value] of data.entries()) {
          const input = document.createElement('input');
          input.type = 'hidden';
          input.name = name;
          input.value = String(value);
          paymentForm.appendChild(input);
        }
        document.body.appendChild(paymentForm);
        // Call the native method explicitly in case a form control shadows "submit".
        HTMLFormElement.prototype.submit.call(paymentForm);
      } catch (error) {
        console.error('Impossible de préparer la redirection Stancer.', error);
        status.className = 'form-status error';
        status.textContent = 'Impossible d’ouvrir le paiement. Aucun règlement n’a été effectué. Réessayez ou envoyez votre demande gratuitement.';
        resetPaymentActions();
        if (switchJourney) switchJourney.hidden = false;
      }
    });
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
    if (journeyMode !== 'guide' || current !== steps.length - 1) return;
    if (!validateCurrentStep()) return;

    status.className = 'form-status';
    status.textContent = 'Envoi en cours…';
    submit.disabled = true;

    try {
      const response = await fetch(form.action, {
        method: 'POST',
        body: new FormData(form),
        headers: {'X-Requested-With': 'XMLHttpRequest'}
      });

      const data = await response.json();
      if (!response.ok || !data.ok) {
        throw new Error(data.message || 'Envoi impossible.');
      }

      status.className = 'form-status success';
      status.textContent = 'Merci. Votre demande a bien été transmise.';
      submit.hidden = true;
      if (payDiagnostic) payDiagnostic.hidden = true;
      prev.hidden = true;
      switchJourney.hidden = true;

      setTimeout(() => {
        form.reset();
        closeModal();
      }, 2200);
    } catch (err) {
      status.className = 'form-status error';
      status.textContent = err.message || 'Une erreur est survenue. Vous pouvez aussi écrire directement à contact@lepotager.org.';
    } finally {
      submit.disabled = false;
    }
  });
})();
