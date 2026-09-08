/**
 * Apprezzamenti.
 *
 * Il conteggio e lo stato (acceso/spento) arrivano da una richiesta a parte,
 * così le pagine possono restare in cache. Questo file viene incluso nella
 * pagina del post: se manca, il pulsante resta comunque visibile quando
 * gli apprezzamenti sono accesi.
 */
(() => {
  'use strict';

  const script = document.currentScript
    || document.querySelector('script[data-endpoint][data-info]');
  const widget = document.querySelector('.upvote');
  const button = widget ? widget.querySelector('[data-uid]') : null;

  if (!script || !widget || !button) {
    return;
  }

  const endpoint = script.getAttribute('data-endpoint');
  const infoUrl = script.getAttribute('data-info');
  if (!endpoint || !infoUrl) {
    return;
  }

  const countEl = widget.querySelector('.upvote-count');
  let token = '';
  let interacted = false;
  let busy = false;

  const markInteracted = () => {
    interacted = true;
  };

  window.addEventListener('pointerdown', markInteracted, { once: true, passive: true });
  window.addEventListener('pointermove', markInteracted, { once: true, passive: true });
  window.addEventListener('keydown', markInteracted, { once: true });
  window.addEventListener('scroll', markInteracted, { once: true, passive: true, capture: true });

  const hide = () => {
    widget.hidden = true;
    button.disabled = true;
    widget.classList.remove('is-voted');
    button.removeAttribute('aria-pressed');
  };

  const apply = (data) => {
    if (!data || typeof data !== 'object') {
      return;
    }
    if (data.enabled === false || data.error === 'disabled') {
      hide();
      return;
    }
    if (data.error) {
      return;
    }
    if (countEl && typeof data.count === 'number') {
      countEl.textContent = String(data.count);
    }
    if (typeof data.token === 'string') {
      token = data.token;
    }
    const voted = !!data.voted;
    button.setAttribute('aria-pressed', voted ? 'true' : 'false');
    widget.classList.toggle('is-voted', voted);
    widget.hidden = false;
    button.disabled = false;
  };

  const request = (url, options) => fetch(url, Object.assign({
    credentials: 'omit',
    cache: 'no-store',
    headers: {
      Accept: 'application/json',
      'X-Requested-With': 'XMLHttpRequest'
    }
  }, options)).then((response) => response.json().catch(() => null));

  const info = infoUrl + (infoUrl.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now();
  request(info).then(apply).catch(() => {
    // Se la richiesta fallisce il pulsante resta come è nell'HTML:
    // visibile se gli apprezzamenti sono accesi, nascosto se sono spenti.
  });

  button.addEventListener('click', () => {
    if (busy || button.disabled || token === '') {
      return;
    }
    busy = true;
    button.disabled = true;

    const body = new URLSearchParams();
    body.set('uid', button.getAttribute('data-uid') || '');
    body.set('token', token);
    body.set('interacted', interacted ? '1' : '0');
    body.set('website', '');

    request(endpoint, {
      method: 'POST',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: body.toString()
    }).then((data) => {
      apply(data);
    }).catch(() => {
      // Niente: il prossimo clic ritenterà con lo stesso token.
    }).then(() => {
      busy = false;
      if (!widget.hidden && button.getAttribute('aria-pressed') !== null) {
        button.disabled = false;
      }
    });
  });
})();
