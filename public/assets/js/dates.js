/**
 * Date nel fuso del lettore.
 *
 * Il server scrive già la data nel formato scelto dall'autore. Questo file
 * aggiunge, in un attributo title, la stessa istante nel fuso di chi legge:
 * utile quando autore e lettore stanno in fusi diversi, innocuo se lo script
 * non gira.
 */
(() => {
  'use strict';

  const nodes = document.querySelectorAll('time[datetime]');
  if (nodes.length === 0) {
    return;
  }

  const locale = document.documentElement.lang || undefined;
  const formatter = new Intl.DateTimeFormat(locale, {
    dateStyle: 'full',
    timeStyle: 'short'
  });

  nodes.forEach((node) => {
    const iso = node.getAttribute('datetime');
    if (!iso) {
      return;
    }
    const date = new Date(iso);
    if (Number.isNaN(date.getTime())) {
      return;
    }
    node.setAttribute('title', formatter.format(date));
  });
})();
