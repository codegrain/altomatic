(function () {
  function addGenerateAllButton() {
    if (!window.Craft || !document.body) return;
    const path = location.pathname.replace(/\/+$/, '');
    if (!/\/admin\/assets$/.test(path)) return;

    const header = document.querySelector('.page-header, header[role="banner"] ~ .content-header, .content-header');
    if (!header) return;

    if (document.querySelector('[data-altomatic-generate-all]')) return;

    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'btn';
    btn.textContent = 'Generate ALT for All (Altomatic)';
    btn.setAttribute('data-altomatic-generate-all', '1');

    btn.addEventListener('click', async () => {
      if (!confirm('Queue ALT generation for ALL images?')) return;
      try {
        const res = await fetch(Craft.getCpUrl('altomatic/generate/queue-all'), {
          method: 'POST',
          headers: {'X-CSRF-Token': Craft.csrfTokenValue, 'Accept': 'application/json'}
        });
        const json = await res.json().catch(() => ({}));
        if (res.ok && json.ok) {
          Craft.cp.displayNotice('Queued ALT generation for all images.');
        } else {
          Craft.cp.displayError(json.error || 'Failed to queue ALT generation.');
        }
      } catch (e) {
        Craft.cp.displayError('Network error.');
      }
    });

    const actions = header.querySelector('.flex .btngroup') || header.querySelector('.btngroup') || header;
    actions.appendChild(btn);
  }

  document.addEventListener('DOMContentLoaded', addGenerateAllButton);
  document.addEventListener('readystatechange', addGenerateAllButton);
})();