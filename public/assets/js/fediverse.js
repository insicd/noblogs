/**
 * Pulsante «Segui sul fediverso»: mostra l'indirizzo ActivityPub del blog
 * e lo copia negli appunti, pronto da incollare in Mastodon e simili.
 */
(() => {
  'use strict';

  const root = document.querySelector('.ap-follow');
  if (!root) {
    return;
  }

  const button = root.querySelector('.ap-follow-btn');
  const panel = root.querySelector('.ap-follow-panel');
  const handleEl = root.querySelector('.ap-follow-handle');
  const status = root.querySelector('.ap-follow-status');
  if (!button || !panel || !handleEl) {
    return;
  }

  const handle = (button.getAttribute('data-handle') || handleEl.textContent || '').trim();
  const copied = button.getAttribute('data-copied') || '';
  if (handle === '') {
    return;
  }

  const show = () => {
    panel.hidden = false;
    button.setAttribute('aria-expanded', 'true');
  };

  const hide = () => {
    panel.hidden = true;
    button.setAttribute('aria-expanded', 'false');
  };

  const selectHandle = () => {
    const selection = window.getSelection();
    if (!selection) {
      return;
    }
    const range = document.createRange();
    range.selectNodeContents(handleEl);
    selection.removeAllRanges();
    selection.addRange(range);
  };

  const copiedNotice = () => {
    if (!status || copied === '') {
      return;
    }
    status.hidden = false;
    status.textContent = copied;
  };

  const copy = () => {
    const fallback = () => {
      selectHandle();
      try {
        if (document.execCommand('copy')) {
          copiedNotice();
        }
      } catch (e) {
        // Resta il testo selezionato, da copiare a mano.
      }
    };

    if (navigator.clipboard && navigator.clipboard.writeText) {
      navigator.clipboard.writeText(handle).then(copiedNotice, fallback);
      return;
    }
    fallback();
  };

  button.addEventListener('click', () => {
    show();
    copy();
  });

  document.addEventListener('click', (event) => {
    if (!(event.target instanceof Node) || !root.contains(event.target)) {
      hide();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && button.getAttribute('aria-expanded') === 'true') {
      hide();
      button.focus();
    }
  });
})();
