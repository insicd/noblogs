/**
 * Editor markdown di Noblogs.
 *
 * Potenzia una <textarea> che funziona già: barra degli strumenti, anteprima,
 * caricamento delle immagini, contatore, salvataggio automatico della bozza.
 * Niente dipendenze, niente CDN, nessun framework.
 *
 * Il markup indispensabile sta nel template, non qui: se questo file non viene
 * eseguito la pagina resta un modulo HTML che si compila e si invia. Quello
 * che si aggiunge sotto è un aiuto, non un requisito.
 *
 * Le modifiche al testo passano da document.execCommand('insertText') quando
 * è disponibile: è l'unico modo di scrivere in una textarea lasciando intatta
 * la cronologia di annullamento del browser. Il ripiego manipola value e
 * ripristina la selezione a mano.
 */
(() => {
  'use strict';

  const PREVIEW_DELAY = 400;
  const AUTOSAVE_DELAY = 5000;
  const WORDS_PER_MINUTE = 200;
  const PREVIEW_KEY = 'noblogs.editor.preview';
  const DRAFT_PREFIX = 'noblogs.draft.';

  const IS_APPLE = /Mac|iPhone|iPad|iPod/.test(navigator.userAgent);
  const MOD = IS_APPLE ? '\u2318' : 'Ctrl';

  /** Marcatore di elemento di elenco, citazione compresa. */
  const MARKER = /^(\s*)((?:[-*+] \[[ xX]\] )|(?:[-*+] )|(?:(\d+)([.)]) )|(?:> ?))(.*)$/;

  /** Qualunque marcatore, da togliere quando se ne applica un altro. */
  const ANY_MARKER = /^(?:[-*+] \[[ xX]\] |[-*+] |\d+[.)] |> ?)/;

  // -----------------------------------------------------------------------
  // Deposito locale: in navigazione privata può lanciare eccezioni, e il
  // salvataggio di una bozza non deve mai poter rompere l'editor.
  // -----------------------------------------------------------------------
  const store = {
    read(key) {
      try {
        return window.localStorage.getItem(key);
      } catch (error) {
        return null;
      }
    },
    write(key, value) {
      try {
        window.localStorage.setItem(key, value);
        return true;
      } catch (error) {
        return false;
      }
    },
    remove(key) {
      try {
        window.localStorage.removeItem(key);
      } catch (error) {
        // Niente da fare: la bozza resterà finché il browser non la scarta.
      }
    }
  };

  const parseJson = (value, fallback) => {
    if (!value) {
      return fallback;
    }
    try {
      const parsed = JSON.parse(value);
      return parsed === null ? fallback : parsed;
    } catch (error) {
      return fallback;
    }
  };

  const element = (tag, className, text) => {
    const node = document.createElement(tag);
    if (className) {
      node.className = className;
    }
    if (text !== undefined && text !== null) {
      node.textContent = text;
    }
    return node;
  };

  const slugify = (value) => {
    const plain = value.normalize
      ? value.normalize('NFD').replace(/[\u0300-\u036f]/g, '')
      : value;

    return plain
      .toLowerCase()
      .replace(/[^a-z0-9]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .slice(0, 200);
  };

  // -----------------------------------------------------------------------
  // Editor
  // -----------------------------------------------------------------------
  class Editor {
    constructor(root) {
      this.root = root;
      this.area = root.querySelector(`#${root.getAttribute('data-target')}`)
        || root.querySelector('textarea');

      if (!this.area) {
        return;
      }

      this.form = this.area.form;
      this.labels = parseJson(root.getAttribute('data-labels'), {});
      this.media = parseJson(root.getAttribute('data-media'), []);
      this.csrf = root.getAttribute('data-csrf') || '';
      this.previewUrl = root.getAttribute('data-preview') || '';
      this.uploadUrl = root.getAttribute('data-upload') || '';
      this.postId = root.getAttribute('data-post-id') || '';
      this.draftKey = DRAFT_PREFIX + (root.getAttribute('data-draft-key') || 'default');
      this.serverSavedAt = parseInt(root.getAttribute('data-updated') || '0', 10) * 1000;

      this.initialValue = this.area.value;
      this.storedValue = this.area.value;
      this.submitting = false;
      this.previewTimer = 0;

      this.build();
      this.bind();
      this.updateCounter();
      this.offerRecoveredDraft();

      if (store.read(PREVIEW_KEY) === '1') {
        this.togglePreview(true);
      }
    }

    label(key) {
      return typeof this.labels[key] === 'string' && this.labels[key] !== ''
        ? this.labels[key]
        : key;
    }

    // --- costruzione dell'interfaccia ----------------------------------

    build() {
      this.toolbar = element('div', 'editor-toolbar');
      this.toolbar.setAttribute('role', 'toolbar');
      this.toolbar.setAttribute('aria-label', this.label('toolbar'));

      this.commands().forEach((command) => {
        this.toolbar.appendChild(
          command.separator ? element('span', 'editor-separator') : this.button(command)
        );
      });

      this.previewToggle = element('button', 'editor-tool editor-preview-toggle', this.label('preview_show'));
      this.previewToggle.type = 'button';
      this.previewToggle.title = this.label('preview_title');
      this.previewToggle.setAttribute('aria-label', this.label('preview_title'));
      this.previewToggle.setAttribute('aria-expanded', 'false');
      this.previewToggle.addEventListener('click', () => this.togglePreview());

      this.toolbar.appendChild(element('span', 'editor-spacer'));
      this.toolbar.appendChild(this.previewToggle);

      this.mediaPanel = this.buildMediaPanel();
      this.insertPanel = this.buildInsertPanel();

      this.draftNotice = element('div', 'editor-draft');
      this.draftNotice.hidden = true;

      // La textarea si sposta dentro un contenitore a due colonne: resta
      // dentro il form, quindi continua a essere inviata come prima.
      this.panes = element('div', 'editor-panes');
      this.previewPane = element('div', 'editor-preview');
      this.previewPane.setAttribute('aria-live', 'polite');
      this.previewPane.hidden = true;

      this.status = element('p', 'editor-status');
      this.counter = element('span', 'editor-counter');
      this.message = element('span', 'editor-message');
      this.status.appendChild(this.counter);
      this.status.appendChild(this.message);

      const anchor = this.area.parentNode;
      anchor.insertBefore(this.draftNotice, this.area);
      anchor.insertBefore(this.toolbar, this.area);
      anchor.insertBefore(this.mediaPanel, this.area);
      anchor.insertBefore(this.insertPanel, this.area);
      anchor.insertBefore(this.panes, this.area);
      anchor.insertBefore(this.status, this.area.nextSibling);

      this.panes.appendChild(this.area);
      this.panes.appendChild(this.previewPane);

      this.root.classList.add('editor-ready');
    }

    button(command) {
      const node = element('button', 'editor-tool', command.icon);
      const name = this.label(command.label);
      const title = command.shortcut ? `${name} (${command.shortcut})` : name;

      node.type = 'button';
      node.title = title;
      node.setAttribute('aria-label', title);
      node.setAttribute('data-command', command.label);
      node.addEventListener('click', (event) => {
        event.preventDefault();
        command.run();
      });

      return node;
    }

    /** Tabella dei pulsanti: icona tipografica, etichetta tradotta, azione. */
    commands() {
      const text = () => this.label('ph_text');

      return [
        { label: 'bold', icon: 'B', shortcut: `${MOD}+B`, run: () => this.wrap('**', '**', text()) },
        { label: 'italic', icon: 'I', shortcut: `${MOD}+I`, run: () => this.wrap('*', '*', text()) },
        { label: 'strike', icon: 'S', run: () => this.wrap('~~', '~~', text()) },
        { label: 'heading', icon: 'H', run: () => this.cycleHeading() },
        { separator: true },
        { label: 'link', icon: '\u2197', shortcut: `${MOD}+K`, run: () => this.insertLink() },
        { label: 'image', icon: '\u25a3', run: () => this.togglePanel(this.mediaPanel) },
        { separator: true },
        { label: 'quote', icon: '\u275d', run: () => this.togglePrefix('> ') },
        { label: 'ul', icon: '\u2022', run: () => this.togglePrefix('- ') },
        { label: 'ol', icon: '1.', run: () => this.numberList() },
        { label: 'task', icon: '\u2610', run: () => this.togglePrefix('- [ ] ') },
        { separator: true },
        { label: 'code', icon: '`', run: () => this.wrap('`', '`', this.label('ph_code')) },
        { label: 'codeblock', icon: '{ }', run: () => this.insertCodeBlock() },
        { label: 'table', icon: '\u25a6', run: () => this.insertTable() },
        { label: 'hr', icon: '\u2014', run: () => this.insertBlock('---') },
        { label: 'footnote', icon: '\u00b9', run: () => this.insertFootnote() },
        { separator: true },
        { label: 'insert', icon: '+', run: () => this.togglePanel(this.insertPanel) }
      ];
    }

    buildMediaPanel() {
      const panel = element('div', 'editor-panel editor-media');
      panel.hidden = true;

      const head = element('div', 'editor-panel-head');
      head.appendChild(element('strong', null, this.label('media_title')));

      const upload = element('button', 'button small', this.label('media_upload'));
      upload.type = 'button';
      upload.addEventListener('click', () => this.filePicker.click());
      head.appendChild(upload);
      panel.appendChild(head);

      this.filePicker = document.createElement('input');
      this.filePicker.type = 'file';
      this.filePicker.multiple = true;
      this.filePicker.hidden = true;
      this.filePicker.addEventListener('change', () => {
        this.uploadFiles(this.filePicker.files);
        this.filePicker.value = '';
      });
      panel.appendChild(this.filePicker);

      if (!this.media.length) {
        panel.appendChild(element('p', 'editor-panel-empty', this.label('media_empty')));
        return panel;
      }

      const list = element('ul', 'editor-media-list');
      this.media.forEach((item) => {
        const choose = element('button', 'editor-media-item');
        choose.type = 'button';
        choose.title = item.name;

        if (item.image) {
          const thumb = document.createElement('img');
          thumb.src = item.url;
          thumb.alt = '';
          thumb.loading = 'lazy';
          choose.appendChild(thumb);
        } else {
          choose.appendChild(element('span', 'editor-media-file', item.name));
        }
        choose.appendChild(element('span', 'editor-media-name', item.name));

        choose.addEventListener('click', () => {
          this.insertAtCursor(item.markdown);
          this.hidePanels();
        });

        const entry = element('li');
        entry.appendChild(choose);
        list.appendChild(entry);
      });

      panel.appendChild(list);
      return panel;
    }

    buildInsertPanel() {
      const panel = element('div', 'editor-panel editor-insert');
      panel.hidden = true;
      panel.appendChild(element('strong', null, this.label('insert')));

      const directives = [
        ['{{ posts }}', 'directive_posts'],
        ['{{ tags }}', 'directive_tags'],
        ['{{ toc }}', 'directive_toc'],
        ['{{ archivio }}', 'directive_archive'],
        ['{{ iscrizione }}', 'directive_subscribe'],
        ['{{ cerca }}', 'directive_search'],
        ['{{ post_nav }}', 'directive_postnav']
      ];

      const list = element('ul', 'editor-directive-list');
      directives.forEach(([code, key]) => {
        const choose = element('button', 'editor-directive');
        choose.type = 'button';
        choose.appendChild(element('code', null, code));
        choose.appendChild(element('span', null, this.label(key)));
        choose.addEventListener('click', () => {
          this.insertBlock(code);
          this.hidePanels();
        });

        const entry = element('li');
        entry.appendChild(choose);
        list.appendChild(entry);
      });

      panel.appendChild(list);
      return panel;
    }

    // --- eventi -------------------------------------------------------

    bind() {
      this.area.addEventListener('input', () => {
        this.updateCounter();
        this.schedulePreview();
      });

      this.area.addEventListener('keydown', (event) => this.onKeydown(event));
      this.area.addEventListener('paste', (event) => this.onPaste(event));

      ['dragenter', 'dragover'].forEach((type) => {
        this.panes.addEventListener(type, (event) => {
          if (hasFiles(event)) {
            event.preventDefault();
            this.panes.classList.add('dropping');
          }
        });
      });

      ['dragleave', 'dragend'].forEach((type) => {
        this.panes.addEventListener(type, () => this.panes.classList.remove('dropping'));
      });

      this.panes.addEventListener('drop', (event) => {
        this.panes.classList.remove('dropping');
        if (!hasFiles(event)) {
          return;
        }
        event.preventDefault();
        this.uploadFiles(event.dataTransfer.files);
      });

      window.setInterval(() => this.saveDraft(), AUTOSAVE_DELAY);

      if (this.form) {
        this.form.addEventListener('submit', () => {
          this.submitting = true;
          store.remove(this.draftKey);
        });
      }

      window.addEventListener('beforeunload', (event) => {
        if (this.submitting || this.area.value === this.initialValue) {
          return undefined;
        }
        // I browser mostrano un testo proprio: il valore serve solo a chiedere.
        event.preventDefault();
        event.returnValue = this.label('unsaved_warning');
        return event.returnValue;
      });
    }

    onKeydown(event) {
      const mod = IS_APPLE ? event.metaKey : event.ctrlKey;

      if (mod && !event.altKey) {
        const key = event.key.toLowerCase();

        if (key === 'b') {
          event.preventDefault();
          this.wrap('**', '**', this.label('ph_text'));
          return;
        }
        if (key === 'i') {
          event.preventDefault();
          this.wrap('*', '*', this.label('ph_text'));
          return;
        }
        if (key === 'k') {
          event.preventDefault();
          this.insertLink();
          return;
        }
        if (key === 's') {
          event.preventDefault();
          this.submitForm();
          return;
        }
      }

      if (event.key === 'Tab' && !mod && !event.altKey) {
        // Fuori da un elenco il Tab resta la navigazione da tastiera:
        // prenderlo sempre farebbe dell'editor una trappola per chi non usa
        // il mouse.
        if (this.indentList(event.shiftKey)) {
          event.preventDefault();
        }
        return;
      }

      if (event.key === 'Enter' && !event.shiftKey && !mod && !event.altKey && this.continueList()) {
        event.preventDefault();
      }
    }

    onPaste(event) {
      const data = event.clipboardData;
      if (!data || !data.files || !data.files.length) {
        return;
      }

      const images = Array.from(data.files).filter((file) => file.type.indexOf('image/') === 0);
      if (!images.length) {
        return;
      }

      event.preventDefault();
      this.uploadFiles(images);
    }

    submitForm() {
      if (!this.form) {
        return;
      }
      this.submitting = true;
      store.remove(this.draftKey);

      if (typeof this.form.requestSubmit === 'function') {
        this.form.requestSubmit();
      } else {
        this.form.submit();
      }
    }

    // --- modifica del testo -------------------------------------------

    /** Sostituisce un intervallo mantenendo l'annullamento del browser. */
    replaceRange(start, end, text) {
      const area = this.area;

      area.focus();
      area.setSelectionRange(start, end);

      let inserted = false;
      if (typeof document.execCommand === 'function') {
        try {
          inserted = document.execCommand('insertText', false, text);
        } catch (error) {
          inserted = false;
        }
      }

      if (!inserted) {
        const before = area.value.slice(0, start);
        const after = area.value.slice(end);
        area.value = before + text + after;
        area.setSelectionRange(start + text.length, start + text.length);
        area.dispatchEvent(new Event('input', { bubbles: true }));
      }

      return start + text.length;
    }

    insertAtCursor(text) {
      const caret = this.replaceRange(this.area.selectionStart, this.area.selectionEnd, text);
      this.area.setSelectionRange(caret, caret);
    }

    /** Avvolge la selezione, o inserisce i delimitatori col cursore in mezzo. */
    wrap(before, after, placeholder) {
      const area = this.area;
      const start = area.selectionStart;
      const end = area.selectionEnd;
      const selected = area.value.slice(start, end);

      if (selected === '') {
        this.replaceRange(start, end, before + placeholder + after);
        area.setSelectionRange(start + before.length, start + before.length + placeholder.length);
        return;
      }

      // Selezione già avvolta: il pulsante fa da interruttore e la scopre.
      const outerStart = start - before.length;
      const outerEnd = end + after.length;
      if (outerStart >= 0
        && area.value.slice(outerStart, start) === before
        && area.value.slice(end, outerEnd) === after) {
        this.replaceRange(outerStart, outerEnd, selected);
        area.setSelectionRange(outerStart, outerStart + selected.length);
        return;
      }

      this.replaceRange(start, end, before + selected + after);
      area.setSelectionRange(start + before.length, start + before.length + selected.length);
    }

    /** Estremi delle righe toccate dalla selezione. */
    lineRange() {
      const value = this.area.value;
      const start = value.lastIndexOf('\n', this.area.selectionStart - 1) + 1;
      const end = value.indexOf('\n', this.area.selectionEnd);

      return [start, end === -1 ? value.length : end];
    }

    selectedLines() {
      const [start, end] = this.lineRange();
      return this.area.value.slice(start, end).split('\n');
    }

    mapLines(transform) {
      const [start, end] = this.lineRange();
      const replaced = this.area.value.slice(start, end).split('\n').map(transform).join('\n');

      this.replaceRange(start, end, replaced);
      this.area.setSelectionRange(start, start + replaced.length);
    }

    togglePrefix(prefix) {
      const lines = this.selectedLines();
      const isTask = prefix.indexOf('[') !== -1;

      const carries = (line) => {
        const body = line.replace(/^\s*/, '');
        if (body.indexOf(prefix) !== 0) {
          return false;
        }
        // '- ' è anche l'inizio di '- [ ] ': senza questo controllo il
        // pulsante dell'elenco puntato smonterebbe un elenco di cose da fare.
        return isTask || !/^[-*+] \[[ xX]\] /.test(body);
      };

      const remove = lines.every((line) => line.trim() === '' || carries(line));

      this.mapLines((line) => {
        if (line.trim() === '') {
          return line;
        }
        const indent = (line.match(/^\s*/) || [''])[0];
        const body = line.slice(indent.length);

        return remove
          ? indent + body.slice(prefix.length)
          : indent + prefix + body.replace(ANY_MARKER, '');
      });
    }

    numberList() {
      const lines = this.selectedLines();
      const remove = lines.every((line) => line.trim() === '' || /^\s*\d+[.)] /.test(line));
      let counter = 0;

      this.mapLines((line) => {
        if (line.trim() === '') {
          return line;
        }
        const indent = (line.match(/^\s*/) || [''])[0];
        const body = line.slice(indent.length);

        if (remove) {
          return indent + body.replace(/^\d+[.)] /, '');
        }
        counter += 1;
        return `${indent}${counter}. ${body.replace(ANY_MARKER, '')}`;
      });
    }

    /** Il pulsante del titolo gira su H2, H3, H4 e poi torna a testo normale. */
    cycleHeading() {
      this.mapLines((line) => {
        const match = line.match(/^(#{1,6})\s+(.*)$/);
        if (!match) {
          return `## ${line.replace(/^\s+/, '')}`;
        }
        const level = match[1].length;

        return level >= 4 ? match[2] : `${'#'.repeat(level + 1)} ${match[2]}`;
      });
    }

    insertLink() {
      const area = this.area;
      const start = area.selectionStart;
      const end = area.selectionEnd;
      const selected = area.value.slice(start, end);
      const url = this.label('ph_url');

      if (selected === '') {
        const placeholder = this.label('ph_text');
        this.replaceRange(start, end, `[${placeholder}](${url})`);
        area.setSelectionRange(start + 1, start + 1 + placeholder.length);
        return;
      }

      // Con del testo selezionato il cursore finisce sull'indirizzo: è il
      // pezzo che manca ancora.
      this.replaceRange(start, end, `[${selected}](${url})`);
      const urlStart = start + selected.length + 3;
      area.setSelectionRange(urlStart, urlStart + url.length);
    }

    insertCodeBlock() {
      const area = this.area;
      const start = area.selectionStart;
      const end = area.selectionEnd;
      const selected = area.value.slice(start, end);
      const body = selected !== '' ? selected : this.label('ph_code');
      const prefix = this.needsBlankLineBefore(start) ? '\n\n' : '';

      this.replaceRange(start, end, `${prefix}\`\`\`\n${body}\n\`\`\`\n`);
      const bodyStart = start + prefix.length + 4;
      area.setSelectionRange(bodyStart, bodyStart + body.length);
    }

    insertTable() {
      const column = this.label('table_column');

      this.insertBlock([
        `| ${column} 1 | ${column} 2 |`,
        '| --- | --- |',
        '|  |  |',
        '|  |  |'
      ].join('\n'));
    }

    insertFootnote() {
      const area = this.area;
      const used = area.value.match(/\[\^(\d+)\]/g) || [];
      let next = 1;

      used.forEach((marker) => {
        const number = parseInt(marker.replace(/\D/g, ''), 10);
        if (number >= next) {
          next = number + 1;
        }
      });

      const reference = `[^${next}]`;
      const caret = area.selectionStart;

      // Prima la definizione in coda, poi il richiamo: scrivendo nell'ordine
      // opposto l'indice del cursore risulterebbe spostato.
      this.replaceRange(area.value.length, area.value.length, `\n\n${reference}: ${this.label('ph_note')}`);
      this.replaceRange(caret, caret, reference);
      area.setSelectionRange(caret + reference.length, caret + reference.length);
    }

    /** Inserisce un blocco isolato da righe vuote, come vuole il markdown. */
    insertBlock(text) {
      const area = this.area;
      const start = area.selectionStart;
      const prefix = this.needsBlankLineBefore(start) ? '\n\n' : '';
      const caret = this.replaceRange(start, area.selectionEnd, `${prefix}${text}\n`);

      area.setSelectionRange(caret, caret);
    }

    needsBlankLineBefore(position) {
      if (position === 0) {
        return false;
      }
      return !/\n\n$|^\n$/.test(this.area.value.slice(Math.max(0, position - 2), position));
    }

    // --- elenchi ------------------------------------------------------

    currentLine() {
      const value = this.area.value;
      const start = value.lastIndexOf('\n', this.area.selectionStart - 1) + 1;

      return { start, text: value.slice(start, this.area.selectionStart) };
    }

    continueList() {
      const line = this.currentLine();
      const match = line.text.match(MARKER);

      if (!match) {
        return false;
      }

      const [, indent, marker, number, delimiter, rest] = match;

      if (rest.trim() === '') {
        // Invio su un elemento vuoto: l'elenco si chiude e la riga si svuota.
        this.replaceRange(line.start, this.area.selectionEnd, '\n');
        return true;
      }

      let next = marker;
      if (number !== undefined) {
        next = `${parseInt(number, 10) + 1}${delimiter} `;
      } else if (/\[[ xX]\]/.test(marker)) {
        next = marker.replace(/\[[xX]\]/, '[ ]');
      }

      this.insertAtCursor(`\n${indent}${next}`);
      return true;
    }

    indentList(outdent) {
      const lines = this.selectedLines();
      const inList = lines.some((line) => MARKER.test(line));

      if (!inList) {
        return false;
      }

      this.mapLines((line) => {
        if (line.trim() === '') {
          return line;
        }
        return outdent ? line.replace(/^ {1,2}/, '') : `  ${line}`;
      });

      return true;
    }

    // --- anteprima ----------------------------------------------------

    togglePreview(force) {
      const visible = force === undefined ? this.previewPane.hidden : force;

      this.previewPane.hidden = !visible;
      this.panes.classList.toggle('split', visible);
      this.previewToggle.textContent = this.label(visible ? 'preview_hide' : 'preview_show');
      this.previewToggle.setAttribute('aria-expanded', visible ? 'true' : 'false');
      store.write(PREVIEW_KEY, visible ? '1' : '0');

      if (visible) {
        this.renderPreview();
      }
    }

    schedulePreview() {
      if (this.previewPane.hidden) {
        return;
      }

      window.clearTimeout(this.previewTimer);
      this.previewTimer = window.setTimeout(() => this.renderPreview(), PREVIEW_DELAY);
    }

    renderPreview() {
      if (this.previewUrl === '' || typeof window.fetch !== 'function') {
        return;
      }

      const body = new FormData();
      body.append('_token', this.csrf);
      body.append('content', this.area.value);
      if (this.postId !== '' && this.postId !== '0') {
        body.append('id', this.postId);
      }

      window.fetch(this.previewUrl, {
        method: 'POST',
        body,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      }).then((response) => {
        if (!response.ok) {
          throw new Error('preview');
        }
        return response.text();
      }).then((html) => {
        // L'HTML arriva dal server, che lo ha già reso e bonificato con le
        // stesse regole della pagina pubblica.
        this.previewPane.innerHTML = html;
      }).catch(() => {
        this.previewPane.textContent = this.label('preview_failed');
      });
    }

    // --- caricamento dei file -----------------------------------------

    uploadFiles(files) {
      if (this.uploadUrl === '' || typeof window.fetch !== 'function') {
        return;
      }

      Array.from(files).forEach((file) => this.uploadOne(file));
    }

    uploadOne(file) {
      const placeholder = `![${this.label('uploading')}]()`;

      this.insertAtCursor(placeholder);
      this.notify(this.label('uploading'));

      const body = new FormData();
      body.append('_token', this.csrf);
      body.append('file[]', file);

      window.fetch(this.uploadUrl, {
        method: 'POST',
        body,
        credentials: 'same-origin',
        headers: {
          'X-Requested-With': 'XMLHttpRequest',
          Accept: 'application/json'
        }
      }).then((response) => response.json().then((data) => {
        if (!response.ok || !data.ok) {
          throw new Error(data.error || this.label('upload_failed'));
        }
        return data;
      })).then((data) => {
        this.swap(placeholder, data.markdown);
        this.notify('');
      }).catch((error) => {
        this.swap(placeholder, '');
        this.notify(error.message || this.label('upload_failed'));
      });
    }

    /** Sostituisce la prima occorrenza di un segnaposto, annullamento incluso. */
    swap(needle, replacement) {
      const at = this.area.value.indexOf(needle);
      if (at === -1) {
        return;
      }

      const caret = this.replaceRange(at, at + needle.length, replacement);
      this.area.setSelectionRange(caret, caret);
    }

    // --- contatore, messaggi, bozza -----------------------------------

    updateCounter() {
      const text = this.area.value.trim();
      const words = text === '' ? 0 : text.split(/\s+/).length;
      const minutes = words === 0 ? 0 : Math.max(1, Math.ceil(words / WORDS_PER_MINUTE));

      this.counter.textContent = `${this.label('words').replace(':count', String(words))}`
        + ` \u00b7 ${this.label('reading_time').replace(':minutes', String(minutes))}`;
    }

    notify(text) {
      this.message.textContent = text;
    }

    saveDraft() {
      if (this.submitting || this.area.value === this.storedValue) {
        return;
      }

      const saved = store.write(this.draftKey, JSON.stringify({
        content: this.area.value,
        savedAt: Date.now()
      }));

      if (saved) {
        this.storedValue = this.area.value;
      }
    }

    /**
     * Bozza trovata nel browser: si propone solo se è più recente dell'ultimo
     * salvataggio sul server, altrimenti recuperarla sarebbe un passo indietro.
     */
    offerRecoveredDraft() {
      const draft = parseJson(store.read(this.draftKey), null);

      if (!draft || typeof draft.content !== 'string') {
        return;
      }
      if (draft.content === this.area.value || !draft.savedAt || draft.savedAt <= this.serverSavedAt) {
        store.remove(this.draftKey);
        return;
      }

      const restore = element('button', 'button small', this.label('draft_restore'));
      restore.type = 'button';
      restore.addEventListener('click', () => {
        this.replaceRange(0, this.area.value.length, draft.content);
        this.draftNotice.hidden = true;
        this.updateCounter();
        this.schedulePreview();
      });

      const discard = element('button', 'button small', this.label('draft_discard'));
      discard.type = 'button';
      discard.addEventListener('click', () => {
        store.remove(this.draftKey);
        this.draftNotice.hidden = true;
      });

      this.draftNotice.appendChild(element('span', null, this.label('draft_found')));
      this.draftNotice.appendChild(restore);
      this.draftNotice.appendChild(discard);
      this.draftNotice.hidden = false;
    }

    // --- pannelli -----------------------------------------------------

    hidePanels() {
      this.mediaPanel.hidden = true;
      this.insertPanel.hidden = true;
    }

    togglePanel(panel) {
      const show = panel.hidden;
      this.hidePanels();
      panel.hidden = !show;
    }
  }

  const hasFiles = (event) =>
    !!(event.dataTransfer && event.dataTransfer.files && event.dataTransfer.files.length);

  // -----------------------------------------------------------------------
  // Accessori delle altre pagine del pannello
  // -----------------------------------------------------------------------

  /** Pulsante che copia il contenuto di un campo di sola lettura. */
  const setupCopyButton = (button) => {
    const field = document.getElementById(button.getAttribute('data-copy'));
    if (!field) {
      return;
    }

    const original = button.textContent;
    const done = () => {
      button.textContent = button.getAttribute('data-copied') || '\u2713';
      window.setTimeout(() => {
        button.textContent = original;
      }, 1500);
    };

    button.addEventListener('click', () => {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(field.value).then(done, () => field.select());
        return;
      }

      field.select();
      try {
        document.execCommand('copy');
        done();
      } catch (error) {
        // Senza appunti programmabili resta la selezione: si copia a mano.
      }
    });
  };

  /** Slug generato dal titolo finché l'autore non lo scrive di suo pugno. */
  const setupSlugSource = (source) => {
    const target = document.getElementById(source.getAttribute('data-slug-source'));
    if (!target) {
      return;
    }

    let manual = target.value.trim() !== '';

    target.addEventListener('input', () => {
      manual = true;
    });

    source.addEventListener('input', () => {
      if (!manual) {
        target.value = slugify(source.value);
      }
    });
  };

  const ready = (callback) => {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback);
      return;
    }
    callback();
  };

  ready(() => {
    document.querySelectorAll('[data-editor]').forEach((root) => new Editor(root));
    document.querySelectorAll('[data-copy]').forEach(setupCopyButton);
    document.querySelectorAll('[data-slug-source]').forEach(setupSlugSource);
  });
})();
