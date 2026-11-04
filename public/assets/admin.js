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

  // Simple HTML editor: toolbar buttons wrap the selected text in tags.
  document.querySelectorAll('.editor').forEach((ed) => {
    const ta = ed.querySelector('.editor-text');
    const preview = ed.querySelector('.editor-preview');
    const file = ed.querySelector('.editor-file');

    const wrap = (before, after, placeholder) => {
      const s = ta.selectionStart; const e = ta.selectionEnd;
      const sel = ta.value.slice(s, e) || placeholder || '';
      ta.setRangeText(before + sel + after, s, e, 'end');
      ta.focus();
      ta.setSelectionRange(s + before.length, s + before.length + sel.length);
    };
    const list = (tag) => {
      const s = ta.selectionStart; const e = ta.selectionEnd;
      const lines = (ta.value.slice(s, e) || 'First item\nSecond item').split(/\n/).filter((l) => l.trim());
      ta.setRangeText('<' + tag + '>\n' + lines.map((l) => '<li>' + l.trim() + '</li>').join('\n') + '\n</' + tag + '>\n', s, e, 'end');
      ta.focus();
    };
    const esc = (s) => s.replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/</g, '&lt;');

    ed.querySelectorAll('.toolbar button').forEach((b) => {
      b.addEventListener('click', () => {
        const cmd = b.dataset.cmd;
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
        } else if (cmd === 'paras') {
          const parts = ta.value.split(/\n\s*\n/).map((p) => p.trim()).filter(Boolean);
          ta.value = parts.map((p) => (/^<(h\d|p|ul|ol|div|blockquote|figure|img|table|section|iframe)/i.test(p) ? p : '<p>' + p.replace(/\n/g, '<br>') + '</p>')).join('\n\n');
        } else if (cmd === 'image') file.click();
        else if (cmd === 'preview') {
          const on = preview.hidden;
          if (on) preview.innerHTML = ta.value;
          preview.hidden = !on; ta.hidden = on;
          b.classList.toggle('on', on);
          b.textContent = on ? 'Back to editing' : 'Preview';
        }
      });
    });

    file.addEventListener('change', async () => {
      if (!file.files.length) return;
      const fd = new FormData();
      fd.append('csrf', ed.dataset.csrf);
      fd.append('action', 'upload-image');
      fd.append('image', file.files[0]);
      try {
        const res = await fetch('/admin', { method: 'POST', body: fd, credentials: 'same-origin' });
        const data = await res.json();
        if (!res.ok) throw new Error(data.error || 'Upload failed');
        const alt = window.prompt('Short description of the image (for Google)', '') || '';
        wrap('<img src="' + esc(data.url) + '" alt="' + esc(alt) + '" loading="lazy">\n', '', '');
      } catch (e) {
        window.alert(e.message || 'Upload failed');
      }
      file.value = '';
    });
  });
})();
