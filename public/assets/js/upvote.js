/**
 * Apprezzamenti. Incluso nella pagina del post (layouts/site.php).
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
  if (!endpoint) {
    return;
  }

  const infoUrl = script.getAttribute('data-info');
  const countEl = widget.querySelector('.upvote-count');
  let token = button.getAttribute('data-token') || '';
  let busy = false;

  const asCount = (value) => {
    const n = parseInt(String(value), 10);
    return Number.isFinite(n) ? n : null;
  };

  const paint = (voted, count) => {
    button.setAttribute('aria-pressed', voted ? 'true' : 'false');
    widget.classList.toggle('is-voted', voted);
    if (countEl && count !== null) {
      countEl.textContent = String(Math.max(0, count));
    }
    widget.hidden = false;
    button.disabled = false;
  };

  const apply = (data) => {
    if (!data || typeof data !== 'object') {
      return false;
    }
    if (data.enabled === false || data.error === 'disabled') {
      widget.hidden = true;
      button.disabled = true;
      return false;
    }
    if (data.error) {
      return false;
    }
    if (typeof data.token === 'string' && data.token !== '') {
      token = data.token;
      button.setAttribute('data-token', data.token);
    }
    paint(!!data.voted, asCount(data.count));
    return true;
  };

  const request = (url, options) => fetch(url, Object.assign({
    credentials: 'same-origin',
    cache: 'no-store',
    headers: { Accept: 'application/json' }
  }, options)).then((response) => response.json().catch(() => null));

  if (infoUrl) {
    const info = infoUrl + (infoUrl.indexOf('?') === -1 ? '?' : '&') + '_=' + Date.now();
    request(info).then(apply).catch(() => {});
  }

  button.addEventListener('click', () => {
    if (busy || button.disabled || token === '' || !endpoint) {
      return;
    }
    busy = true;
    button.disabled = true;

    const wasVoted = button.getAttribute('aria-pressed') === 'true';
    const nextVoted = !wasVoted;
    const current = asCount(countEl ? countEl.textContent : '0') ?? 0;
    paint(nextVoted, current + (nextVoted ? 1 : -1));

    const body = new URLSearchParams();
    body.set('uid', button.getAttribute('data-uid') || '');
    body.set('token', token);
    body.set('interacted', '1');
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
        paint(wasVoted, current);
      }
    }).catch(() => {
      paint(wasVoted, current);
    }).then(() => {
      busy = false;
      if (!widget.hidden) {
        button.disabled = false;
      }
    });
  });
})();
