/**
 * Apprezzamenti.
 *
 * Il token sta già sul pulsante, così il clic funziona anche se la richiesta
 * di stato non arriva. Quella richiesta serve solo ad aggiornare il conteggio
 * e a marcare il pulsante se hai già votato.
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
  if (!endpoint) {
    return;
  }

  const countEl = widget.querySelector('.upvote-count');
  let token = button.getAttribute('data-token') || '';
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

  const asCount = (value) => {
    const n = Number(value);
    return Number.isFinite(n) ? n : null;
  };

  const apply = (data) => {
    if (!data || typeof data !== 'object') {
      return false;
    }
    if (data.enabled === false || data.error === 'disabled') {
      hide();
      return false;
    }
    if (data.error) {
      return false;
    }
    const count = asCount(data.count);
    if (countEl && count !== null) {
      countEl.textContent = String(count);
    }
    if (typeof data.token === 'string' && data.token !== '') {
      token = data.token;
      button.setAttribute('data-token', data.token);
    }
    const voted = !!data.voted;
    button.setAttribute('aria-pressed', voted ? 'true' : 'false');
    widget.classList.toggle('is-voted', voted);
    widget.hidden = false;
    button.disabled = false;
    return true;
  };

  const request = (url, options) => fetch(url, Object.assign({
    credentials: 'same-origin',
    cache: 'no-store',
    headers: {
      Accept: 'application/json'
    }
  }, options)).then((response) => response.json().catch(() => null));

  if (infoUrl) {
    const info = infoUrl + (infoUrl.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now();
    request(info).then(apply).catch(() => {});
  }

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
        'Content-Type': 'application/x-www-form-urlencoded'
      },
      body: body.toString()
    }).then((data) => {
      if (!apply(data)) {
        button.disabled = false;
      }
    }).catch(() => {
      button.disabled = false;
    }).then(() => {
      busy = false;
      if (!widget.hidden) {
        button.disabled = false;
      }
    });
  });
})();
