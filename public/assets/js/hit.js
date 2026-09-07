/**
 * Conteggio delle letture.
 *
 * Parte solo dopo un movimento, uno scorrimento o un tasto: i programmi che
 * scaricano la pagina non fanno nessuna di queste cose, e così restano fuori
 * dalle statistiche senza bisogno di una lista di bot da aggiornare.
 *
 * Non usa cookie, non legge lo storage, non identifica il visitatore. L'unica
 * informazione inviata è l'articolo letto e, se c'è, il sito di provenienza.
 */
(() => {
  'use strict';

  const script = document.querySelector('script[src*="/js/hit.js"]');
  if (!script) {
    return;
  }

  const endpoint = script.getAttribute('data-endpoint');
  if (!endpoint) {
    return;
  }

  const uid = script.getAttribute('data-uid') || '';
  let sent = false;

  const send = () => {
    if (sent) {
      return;
    }
    sent = true;

    const body = new URLSearchParams();
    if (uid !== '') {
      body.set('uid', uid);
    }
    if (document.referrer) {
      body.set('ref', document.referrer);
    }

    const sendBeacon = window.navigator && typeof window.navigator.sendBeacon === 'function';
    if (sendBeacon) {
      const blob = new Blob([body.toString()], {
        type: 'application/x-www-form-urlencoded'
      });
      if (window.navigator.sendBeacon(endpoint, blob)) {
        return;
      }
    }

    fetch(endpoint, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded',
        'X-Requested-With': 'XMLHttpRequest'
      },
      body: body.toString(),
      credentials: 'omit',
      keepalive: true
    }).catch(() => {
      // Il conteggio è accessorio: se la richiesta fallisce la pagina resta
      // comunque leggibile.
    });
  };

  const onInteract = () => {
    window.removeEventListener('pointerdown', onInteract);
    window.removeEventListener('pointermove', onInteract);
    window.removeEventListener('keydown', onInteract);
    window.removeEventListener('scroll', onInteract, true);
    send();
  };

  window.addEventListener('pointerdown', onInteract, { passive: true });
  window.addEventListener('pointermove', onInteract, { passive: true });
  window.addEventListener('keydown', onInteract);
  window.addEventListener('scroll', onInteract, { passive: true, capture: true });
})();
