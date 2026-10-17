(() => {
  const key = 'rgpd-accessible-mode';
  const eye = '<svg class="rgpd-a11y-eye" aria-hidden="true" focusable="false" viewBox="0 0 24 24" width="24" height="24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M2 12s3.6-7 10-7 10 7 10 7-3.6 7-10 7S2 12 2 12Z"/><circle cx="12" cy="12" r="3"/></svg>';
  let toggle = document.querySelector('[data-rgpd-accessibility-toggle]');
  if (!toggle) {
    // Pages légales sans l'en-tête partagé.
    toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'rgpd-a11y-floating';
    toggle.dataset.rgpdAccessibilityToggle = '';
    toggle.innerHTML = eye;
    document.body.append(toggle);
  }
  const apply = enabled => {
    document.documentElement.dataset.rgpdAccessible = String(enabled);
    toggle.setAttribute('aria-pressed', String(enabled));
    const label = enabled ? 'Désactiver la version accessible' : 'Activer la version accessible';
    toggle.setAttribute('aria-label', label);
    toggle.setAttribute('title', label);
  };
  let saved = false;
  try { saved = localStorage.getItem(key) === 'true'; } catch (_) {}
  apply(saved);
  toggle.addEventListener('click', () => {
    const enabled = document.documentElement.dataset.rgpdAccessible !== 'true';
    apply(enabled);
    try { localStorage.setItem(key, String(enabled)); } catch (_) {}
  });
})();
