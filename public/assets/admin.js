(() => {
  'use strict';

  // Ask before delete / reset buttons.
  document.addEventListener('click', (ev) => {
    const btn = ev.target.closest('button[data-confirm]');
    if (btn && !window.confirm(btn.dataset.confirm)) ev.preventDefault();
  });
  document.addEventListener('submit', (ev) => {
    const form = ev.target;
    if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) ev.preventDefault();
  });

  // Slug follows the title for new posts until the owner types one.
  const title = document.getElementById('f-title');
  const slug = document.getElementById('f-slug');
  const slugify = (s) => s.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '').slice(0, 80);
  if (title && slug) {
    const show = () => { document.getElementById('slug-preview').textContent = slug.value || slugify(title.value) || 'post'; };
    title.addEventListener('input', () => { if (slug.dataset.auto === '1') slug.value = slugify(title.value); show(); });
    slug.addEventListener('input', () => { slug.dataset.auto = '0'; show(); });
  }

  // Post status: show the date box only for Schedule, and name the save button after the choice.
  const status = document.getElementById('f-status');
  if (status) {
    const box = document.getElementById('sched-box');
    const save = document.getElementById('f-save');
    const auto = document.getElementById('f-auto');
    const date = document.getElementById('f-date');
    const upd = () => {
      box.hidden = status.value !== 'schedule';
      save.textContent = { now: 'Publish Now', schedule: 'Schedule post', draft: 'Save draft' }[status.value];
      if (date && auto) date.disabled = auto.checked && status.value === 'schedule';
    };
    status.addEventListener('change', upd);
    if (auto) auto.addEventListener('change', upd);
    upd();
  }

  // Online Now counter on the Visitors tab.
  const online = document.querySelector('[data-online]');
  if (online) {
    setInterval(async () => {
      try {
        const r = await fetch('/admin?tab=visitors&online=1', { credentials: 'same-origin' });
        if (r.ok) online.textContent = (await r.json()).online;
      } catch (e) { /* ignore */ }
    }, 15000);
  }

  // Editor: Visual (editable preview, like the WordPress classic editor), Code (HTML) or Split. Always in sync.
  document.querySelectorAll('.editor').forEach((ed) => {
    const ta = ed.querySelector('.editor-text');
    const vis = ed.querySelector('.editor-visual');
    const file = ed.querySelector('.editor-file');
    let mode = 'visual'; let last = 'vis';
    try { document.execCommand('defaultParagraphSeparator', false, 'p'); } catch (e) { /* old browser */ }
    const esc = (s) => s.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');
    const toVis = () => { vis.innerHTML = ta.value; };
    const toCode = () => { const h = vis.innerHTML; ta.value = h === '<br>' ? '' : h; };

    const setMode = (m) => {
      if (m === 'code' && mode !== 'code') toCode();
      if (m !== 'code' && mode === 'code') toVis();
      mode = m;
      vis.hidden = m === 'code';
      ta.hidden = m === 'visual';
      ed.querySelectorAll('.ed-tabs button').forEach((b) => b.classList.toggle('on', b.dataset.mode === m));
    };
    ed.querySelectorAll('.ed-tabs button').forEach((b) => b.addEventListener('click', () => setMode(b.dataset.mode)));
    vis.addEventListener('input', toCode);
    ta.addEventListener('input', () => { if (mode === 'split') toVis(); });
    vis.addEventListener('focus', () => { last = 'vis'; });
    ta.addEventListener('focus', () => { last = 'ta'; });
    const form = ed.closest('form');
    if (form) form.addEventListener('submit', () => { if (mode !== 'code') toCode(); });
    toVis();
    setMode('visual');

    const inVis = () => mode === 'visual' || (mode === 'split' && last === 'vis');

    // --- code (textarea) helpers
    const wrap = (before, after, placeholder) => {
      const s = ta.selectionStart; const e = ta.selectionEnd;
      const sel = ta.value.slice(s, e) || placeholder || '';
      ta.setRangeText(before + sel + after, s, e, 'end');
      ta.focus();
      ta.setSelectionRange(s + before.length, s + before.length + sel.length);
      if (mode === 'split') toVis();
    };
    const list = (tag) => {
      const s = ta.selectionStart; const e = ta.selectionEnd;
      const lines = (ta.value.slice(s, e) || 'First item\nSecond item').split(/\n/).filter((l) => l.trim());
      ta.setRangeText('<' + tag + '>\n' + lines.map((l) => '<li>' + l.trim() + '</li>').join('\n') + '\n</' + tag + '>\n', s, e, 'end');
      ta.focus();
      if (mode === 'split') toVis();
    };

    // --- visual helpers
    const exec = (cmd, val) => { vis.focus(); document.execCommand(cmd, false, val); toCode(); };
    const askUrl = () => {
      const sel = window.getSelection();
      const saved = sel.rangeCount ? sel.getRangeAt(0).cloneRange() : null;
      const url = window.prompt('Link address (https://... or /page)', 'https://');
      vis.focus();
      if (saved) { sel.removeAllRanges(); sel.addRange(saved); }
      return url;
    };
    const selText = () => String(window.getSelection());

    ed.querySelectorAll('.toolbar button').forEach((b) => {
      b.addEventListener('click', () => {
        const cmd = b.dataset.cmd;
        if (cmd === 'paras') {
          if (mode !== 'code') toCode();
          const parts = ta.value.split(/\n\s*\n/).map((p) => p.trim()).filter(Boolean);
          ta.value = parts.map((p) => (/^<(h\d|p|ul|ol|div|blockquote|figure|img|table|section|iframe)/i.test(p) ? p : '<p>' + p.replace(/\n/g, '<br>') + '</p>')).join('\n\n');
          if (mode !== 'code') toVis();
          return;
        }
        if (cmd === 'image') { file.click(); return; }
        if (inVis()) {
          if (cmd === 'h2' || cmd === 'h3' || cmd === 'p') exec('formatBlock', '<' + cmd + '>');
          else if (cmd === 'b') exec('bold');
          else if (cmd === 'i') exec('italic');
          else if (cmd === 'quote') exec('formatBlock', '<blockquote>');
          else if (cmd === 'ul') exec('insertUnorderedList');
          else if (cmd === 'ol') exec('insertOrderedList');
          else if (cmd === 'link') { const url = askUrl(); if (url) exec('createLink', url); }
          else if (cmd === 'button') {
            const url = askUrl(); if (!url) return;
            const ext = /^https?:/i.test(url) ? ' target="_blank" rel="noopener"' : '';
            exec('insertHTML', '<a href="' + esc(url) + '" class="btn btn-primary"' + ext + '>' + (esc(selText()) || 'Button text') + '</a>');
          }
          return;
        }
        if (cmd === 'h2' || cmd === 'h3' || cmd === 'p') wrap('<' + cmd + '>', '</' + cmd + '>\n', cmd === 'p' ? 'Paragraph text' : 'Heading');
        else if (cmd === 'b') wrap('<strong>', '</strong>', 'bold text');
        else if (cmd === 'i') wrap('<em>', '</em>', 'text');
        else if (cmd === 'quote') wrap('<blockquote>', '</blockquote>\n', 'Quote');
        else if (cmd === 'ul' || cmd === 'ol') list(cmd);
        else if (cmd === 'link' || cmd === 'button') {
          const url = window.prompt('Link address (https://... or /page)', 'https://');
          if (!url) return;
          const cls = cmd === 'button' ? ' class="btn btn-primary"' : '';
          const ext = /^https?:/i.test(url) ? ' target="_blank" rel="noopener"' : '';
          wrap('<a href="' + esc(url) + '"' + cls + ext + '>', '</a>', cmd === 'button' ? 'Button text' : 'link text');
        }
      });
    });

    file.addEventListener('change', async () => {
      if (!file.files.length) return;
      const asVis = inVis();
      const sel = window.getSelection();
      const saved = asVis && sel.rangeCount ? sel.getRangeAt(0).cloneRange() : null;
      const fd = new FormData();
      fd.append('csrf', ed.dataset.csrf);
      fd.append('action', 'upload-image');
      fd.append('image', file.files[0]);
      try {
        const res = await fetch('/admin', { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Upload failed');
        const alt = window.prompt('Short description of the image (for Google)', '') || '';
        const html = '<img src="' + esc(data.url) + '" alt="' + esc(alt) + '" loading="lazy">';
        if (asVis) {
          vis.focus();
          if (saved) { sel.removeAllRanges(); sel.addRange(saved); }
          document.execCommand('insertHTML', false, html);
          toCode();
        } else {
          wrap(html + '\n', '', '');
        }
      } catch (e) {
        window.alert(e.message || 'Upload failed');
      }
      file.value = '';
    });
  });
})();
