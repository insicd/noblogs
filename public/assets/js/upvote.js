/**
 * Apprezzamenti.
 *
 * Il conteggio non è nell'HTML della pagina: arriva da una richiesta a parte,
 * così le pagine possono restare in cache per ore e il numero mostrato è
 * comunque quello di adesso. Lo stesso vale per l'opzione del blog: se gli
 * apprezzamenti sono spenti, questa richiesta lo dice e il pulsante resta
 * nascosto anche su una pagina già in cache.
 *
 * Senza questo file il pulsante resta nascosto e la pagina continua a funzionare.
 */
(() => {
  'use strict';

  const script = document.querySelector('script[src*="/js/upvote.js"]');
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
    // Senza il conteggio il pulsante resta nascosto: meglio niente che un
    // numero sbagliato, o un pulsante su un blog che li ha disattivati.
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
