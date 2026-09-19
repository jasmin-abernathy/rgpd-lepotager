
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
  const stepNumber = document.getElementById('stepNumber');
  const progressBar = document.getElementById('progressBar');
  const resultCard = document.getElementById('resultCard');
  const recommendationField = document.getElementById('recommendationField');
  const summaryField = document.getElementById('summaryField');
  const status = document.getElementById('formStatus');
  const startedAt = document.getElementById('startedAt');

  let current = 0;
  let lastFocused = null;

  const labels = {
    cms: 'CMS / site',
    tools: 'Outils / usages',
    register: 'Registre',
    retention: 'Durées de conservation',
    processors: 'Prestataires / destinataires',
    trackers: 'Traceurs connus',
    forms_info: 'Information sous formulaires'
  };

  function openModal() {
    lastFocused = document.activeElement;
    modal.hidden = false;
    document.body.style.overflow = 'hidden';
    startedAt.value = String(Date.now());
    showStep(0);
    setTimeout(() => modal.querySelector('.wizard-close')?.focus(), 30);
  }

  function closeModal() {
    modal.hidden = true;
    document.body.style.overflow = '';
    if (lastFocused) lastFocused.focus();
  }

  openButtons.forEach(btn => btn.addEventListener('click', openModal));
  closeButtons.forEach(btn => btn.addEventListener('click', closeModal));

  // Permet au bouton du comparateur de revenir sur la racine et d’ouvrir directement le diagnostic.
  if (window.location.hash === '#diagnostic') {
    openModal();
  }

  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && !modal.hidden) closeModal();
  });

  function showStep(index) {
    current = Math.max(0, Math.min(index, steps.length - 1));
    steps.forEach((step, i) => step.classList.toggle('is-active', i === current));
    stepNumber.textContent = String(current + 1);
    progressBar.style.width = `${((current + 1) / steps.length) * 100}%`;
    prev.hidden = current === 0;
    next.hidden = current === steps.length - 1;
    submit.hidden = current !== steps.length - 1;
    if (payDiagnostic) payDiagnostic.hidden = current !== steps.length - 1;

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
          ? 'Plusieurs flux ou points d’organisation semblent se cumuler. Le Pack Complet permet de traiter la partie documentaire, les prestataires et l’audit technique. Sur WordPress, Pixel Trackers Manager peut être installé dans le site et utilisé lors des revues planifiées ; il ne fournit pas encore de surveillance distante automatique.'
          : 'Plusieurs flux ou points d’organisation semblent se cumuler. Le Pack Complet permet une revue plus approfondie. Pixel Trackers Manager n’est pas inclus car il est réservé à WordPress.'
      };
    }

    return {
      title: 'Le Pack Essentiel semble être le bon point de départ',
      price: '690 €',
      code: 'Pack Essentiel 690 €',
      text: cms === 'WordPress'
        ? 'Votre situation paraît compatible avec une remise au propre ciblée. Sur WordPress, Pixel Trackers Manager peut être installé et configuré dans le site pour être consulté lors des revues planifiées ; il ne remonte pas encore les informations à distance.'
        : 'Votre situation paraît compatible avec une remise au propre ciblée. La partie technique sera traitée sans Pixel Trackers Manager si le site n’est pas sous WordPress.'
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
    if (!validateCurrentStep()) return;
    showStep(current + 1);
  });

  prev.addEventListener('click', () => showStep(current - 1));

  function validateFinalContact() {
    if (!validateCurrentStep()) return false;
    const email = form.querySelector('[name="email"]');
    const privacy = form.querySelector('[name="privacy_ack"]');
    if (email && !email.checkValidity()) {
      email.reportValidity();
      return false;
    }
    if (privacy && !privacy.checked) {
      privacy.focus();
      return false;
    }
    return true;
  }

  if (payDiagnostic) {
    payDiagnostic.addEventListener('click', () => {
      if (!validateFinalContact()) return;
      buildResult();
      status.className = 'form-status';
      status.textContent = 'Préparation du paiement sécurisé…';
      payDiagnostic.disabled = true;

      const paymentForm = document.createElement('form');
      paymentForm.method = 'post';
      paymentForm.action = 'paiement.php';
      paymentForm.hidden = true;

      const data = new FormData(form);
      data.set('service', 'diagnostic-personnalise');
      for (const [name, value] of data.entries()) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = String(value);
        paymentForm.appendChild(input);
      }
      document.body.appendChild(paymentForm);
      paymentForm.submit();
    });
  }

  form.addEventListener('submit', async (e) => {
    e.preventDefault();
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
