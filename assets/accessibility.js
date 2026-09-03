(() => {
  const key = 'rgpd-accessible-mode';
  let toggle = document.querySelector('[data-rgpd-accessibility-toggle]');
  if (!toggle) {
    toggle = document.createElement('button');
    toggle.type = 'button';
    toggle.className = 'rgpd-a11y-floating';
    toggle.dataset.rgpdAccessibilityToggle = '';
    document.body.append(toggle);
  }
  const apply = enabled => {
    document.documentElement.dataset.rgpdAccessible = String(enabled);
    toggle.setAttribute('aria-pressed', String(enabled));
    toggle.textContent = enabled ? 'Version standard' : 'Version accessible';
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
