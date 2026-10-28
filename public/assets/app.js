(() => {
  'use strict';

  const $ = (id) => document.getElementById(id);
  const state = { boxes: [], current: null, messages: [], openId: null, poll: 5, timer: null, domains: [], known: new Set() };

  // ---------- helpers ----------
  async function api(method, path, body) {
    const res = await fetch(path, {
      method,
      headers: method === 'GET' ? {} : { 'Content-Type': 'application/json', 'X-TMS': '1' },
      body: body ? JSON.stringify(body) : undefined,
      credentials: 'same-origin',
    });
    let data = {};
    try { data = await res.json(); } catch (e) { /* empty body */ }
    if (!res.ok) throw new Error(data.error || 'Request failed');
    return data;
  }

  function toast(text) {
    const t = $('toast');
    if (!t) return;
    t.textContent = text;
    t.classList.add('show');
    clearTimeout(toast.t);
    toast.t = setTimeout(() => t.classList.remove('show'), 1800);
  }

  async function copy(text, label) {
    try {
      await navigator.clipboard.writeText(text);
    } catch (e) {
      const ta = document.createElement('textarea');
      ta.value = text; document.body.appendChild(ta); ta.select();
      document.execCommand('copy'); ta.remove();
    }
    toast(label || 'Copied');
  }

  function ago(ts) {
    const s = Math.max(0, Math.floor(Date.now() / 1000 - ts));
    if (s < 60) return 'just now';
    if (s < 3600) return Math.floor(s / 60) + ' min ago';
    if (s < 86400) return Math.floor(s / 3600) + ' h ago';
    return Math.floor(s / 86400) + ' d ago';
  }

  function left(ts) {
    const s = Math.max(0, ts - Math.floor(Date.now() / 1000));
    if (s >= 86400) return Math.round(s / 86400) + ' days';
    if (s >= 3600) return Math.round(s / 3600) + ' hours';
    return Math.max(1, Math.round(s / 60)) + ' min';
  }

  function icon(domain) {
    return domain ? 'https://www.google.com/s2/favicons?sz=64&domain=' + encodeURIComponent(domain) : '/assets/icon.svg';
  }

  function el(tag, cls, text) {
    const n = document.createElement(tag);
    if (cls) n.className = cls;
    if (text != null) n.textContent = text;
    return n;
  }

  // ---------- inbox app ----------
  const app = $('app');

  async function init() {
    const s = await api('GET', '/api/state');
    state.poll = s.poll_seconds || 5;
    state.domains = s.domains || [];
    state.boxes = s.mailboxes || [];
    fillDomains(s.allow_custom);
    updateAccount(s.account);
    if (!state.boxes.length) {
      await newBox();
    } else {
      const saved = localGet('tms_box');
      select(state.boxes.find((b) => String(b.id) === saved) || state.boxes[0]);
    }
  }

  function fillDomains(allowCustom) {
    const sel = $('domain-select');
    sel.innerHTML = '';
    state.domains.forEach((d) => { const o = el('option', '', d); o.value = d; sel.appendChild(o); });
    if (!allowCustom) $('btn-change').hidden = true;
  }

  function renderBoxes() {
    const sel = $('box-select');
    sel.innerHTML = '';
    state.boxes.forEach((b) => {
      const o = el('option', '', b.address + (b.unread ? ' (' + b.unread + ')' : ''));
      o.value = b.id;
      o.selected = state.current && b.id === state.current.id;
      sel.appendChild(o);
    });
    sel.hidden = state.boxes.length < 2;
  }

  function select(box) {
    state.current = box;
    state.messages = [];
    state.known = new Set();
    localSet('tms_box', String(box.id));
    $('addr').value = box.address;
    $('addr-expiry').textContent = 'Active · expires in ' + left(box.expires_at);
    $('mail-list').innerHTML = '';
    closeReader();
    renderBoxes();
    refresh(true);
  }

  async function newBox(name, domain) {
    const r = await api('POST', '/api/mailboxes', name ? { name, domain } : {});
    state.boxes.unshift(r.mailbox);
    select(r.mailbox);
    return r.mailbox;
  }

  async function refresh(first) {
    if (!state.current) return;
    try {
      const r = await api('GET', '/api/mailboxes/' + state.current.id);
      const fresh = r.messages.filter((m) => !state.known.has(m.id));
      if (!first && fresh.length) notify(fresh[0]);
      r.messages.forEach((m) => state.known.add(m.id));
      state.messages = r.messages;
      state.current.expires_at = r.mailbox.expires_at;
      renderList();
      $('inbox-status').textContent = 'Updated ' + new Date().toLocaleTimeString();
    } catch (e) {
      if (/not found/i.test(e.message)) {
        state.boxes = state.boxes.filter((b) => b.id !== state.current.id);
        state.current = null;
        state.boxes.length ? select(state.boxes[0]) : newBox().catch(showError);
      } else {
        $('inbox-status').textContent = 'Offline, retrying…';
      }
    }
  }

  function renderList() {
    const list = $('mail-list');
    list.innerHTML = '';
    $('empty').hidden = state.messages.length > 0;
    state.messages.forEach((m) => {
      const li = el('li', 'mail' + (m.seen ? '' : ' unread'));
      const img = el('img', 'favicon');
      img.src = icon(m.domain); img.alt = ''; img.loading = 'lazy'; img.referrerPolicy = 'no-referrer';
      img.onerror = () => { img.src = '/assets/icon.svg'; };
      const main = el('div', 'mail-main');
      const top = el('div', 'mail-top');
      top.append(el('span', 'mail-from', m.from_name), el('span', 'muted small', ago(m.received_at)));
      main.append(top, el('div', 'mail-subject', m.subject), el('div', 'mail-preview', m.preview));
      if (m.otp) {
        const chip = el('button', 'chip', m.otp + '  ⧉');
        chip.type = 'button';
        chip.title = 'Copy code';
        chip.onclick = (ev) => { ev.stopPropagation(); copy(m.otp, 'Code copied'); };
        main.appendChild(chip);
      }
      li.append(img, main);
      li.onclick = () => openMessage(m.id);
      list.appendChild(li);
    });
  }

  async function openMessage(id) {
    const r = await api('GET', '/api/messages/' + id);
    const m = r.message;
    state.openId = id;
    const known = state.messages.find((x) => x.id === id);
    if (known) known.seen = true;
    $('r-subject').textContent = m.subject;
    $('r-from').textContent = m.from_name;
    $('r-addr').textContent = m.from_addr;
    $('r-time').textContent = ago(m.received_at);
    $('r-icon').src = icon(m.domain);
    $('r-otp').hidden = !m.otp;
    $('r-otp-code').textContent = m.otp || '';
    $('r-link').hidden = !m.link;
    if (m.link) $('r-link-a').href = m.link;
    $('r-text').textContent = m.text || '';
    $('r-tabs').hidden = !m.has_html;
    showView(m.has_html ? 'html' : 'text');
    $('r-html').src = m.has_html ? '/m/' + id + '/html' : 'about:blank';
    const atts = $('r-atts');
    atts.innerHTML = '';
    m.attachments.forEach((a) => {
      const li = el('li');
      const link = el('a', '', '📎 ' + a.filename + ' (' + Math.ceil(a.size / 1024) + ' KB)');
      link.href = a.url;
      li.appendChild(link);
      atts.appendChild(li);
    });
    document.querySelector('.inbox').hidden = true;
    $('reader').hidden = false;
    $('reader').scrollIntoView({ behavior: 'smooth', block: 'start' });
  }

  function showView(view) {
    $('r-html').hidden = view !== 'html';
    $('r-text').hidden = view !== 'text';
    document.querySelectorAll('#r-tabs button').forEach((b) => b.classList.toggle('on', b.dataset.view === view));
  }

  function closeReader() {
    state.openId = null;
    $('reader').hidden = true;
    $('r-html').src = 'about:blank';
    document.querySelector('.inbox').hidden = false;
    renderList();
  }

  function notify(m) {
    toast('New email: ' + m.subject);
    if ('Notification' in window && Notification.permission === 'granted' && document.hidden) {
      try { new Notification(m.from_name, { body: m.otp ? 'Code: ' + m.otp : m.subject, icon: '/assets/icon-192.png' }); } catch (e) { /* ignore */ }
    }
  }

  function loop() {
    clearTimeout(state.timer);
    state.timer = setTimeout(async () => {
      if (!document.hidden) await refresh(false);
      loop();
    }, state.poll * 1000);
  }

  function showError(e) { toast(e.message || 'Something went wrong'); }

  function localGet(k) { try { return localStorage.getItem(k); } catch (e) { return null; } }
  function localSet(k, v) { try { localStorage.setItem(k, v); } catch (e) { /* private mode */ } }

  if (app) {
    $('btn-copy').onclick = () => {
      if (state.current) copy(state.current.address, 'Address copied');
      if ('Notification' in window && Notification.permission === 'default') Notification.requestPermission();
    };
    $('btn-new').onclick = () => newBox().then(() => toast('New address ready')).catch(showError);
    $('btn-refresh').onclick = () => refresh(false).then(() => toast('Inbox refreshed'));
    $('btn-change').onclick = () => { const f = $('custom-form'); f.hidden = !f.hidden; if (!f.hidden) f.elements.name.focus(); };
    $('btn-delete').onclick = async () => {
      if (!state.current || !window.confirm('Delete ' + state.current.address + ' and all its email?')) return;
      try {
        await api('DELETE', '/api/mailboxes/' + state.current.id);
        state.boxes = state.boxes.filter((b) => b.id !== state.current.id);
        state.current = null;
        state.boxes.length ? select(state.boxes[0]) : await newBox();
        toast('Address deleted');
      } catch (e) { showError(e); }
    };
    $('custom-form').onsubmit = async (ev) => {
      ev.preventDefault();
      const f = ev.target;
      try {
        await newBox(f.elements.name.value.trim(), f.domain.value);
        f.reset(); f.hidden = true;
        toast('Address created');
      } catch (e) { showError(e); }
    };
    $('box-select').onchange = (ev) => { const b = state.boxes.find((x) => String(x.id) === ev.target.value); if (b) select(b); };
    $('btn-back').onclick = closeReader;
    $('btn-copy-otp').onclick = () => copy($('r-otp-code').textContent, 'Code copied');
    $('btn-del-msg').onclick = async () => {
      if (!state.openId) return;
      await api('DELETE', '/api/messages/' + state.openId).catch(showError);
      state.messages = state.messages.filter((m) => m.id !== state.openId);
      closeReader();
    };
    document.querySelectorAll('#r-tabs button').forEach((b) => { b.onclick = () => showView(b.dataset.view); });
    document.addEventListener('visibilitychange', () => { if (!document.hidden) refresh(false); });
    init().then(loop).catch(showError);

    const q = new URLSearchParams(location.search);
    if (q.get('linked') === '1') toast('This device is now linked');
    if (q.get('linked') === '0') toast('That link has expired. Make a new one.');
  }

  // ---------- account dialog ----------
  function updateAccount(info) {
    const s = $('account-status');
    if (!s || !info) return;
    s.textContent = info.linked
      ? 'Linked account · ' + info.devices + ' device(s). Mailboxes last 30 days.'
      : 'Guest mode. Save a recovery code or link a device to keep mailboxes for 30 days.';
  }

  document.querySelectorAll('[data-open]').forEach((b) => {
    b.addEventListener('click', () => $(b.dataset.open).showModal());
  });

  const recoveryBtn = $('btn-recovery');
  if (recoveryBtn) {
    recoveryBtn.onclick = async () => {
      if (!window.confirm('Make a new recovery code? Any older code stops working.')) return;
      try {
        const r = await api('POST', '/api/account/recovery-code');
        const out = $('recovery-out');
        out.hidden = false;
        out.textContent = r.code;
        out.title = 'Click to copy';
        out.onclick = () => copy(r.code, 'Recovery code copied');
        updateAccount(r.account);
        toast('Save this code somewhere safe');
      } catch (e) { showError(e); }
    };
    $('btn-link').onclick = async () => {
      try {
        const r = await api('POST', '/api/account/link-code');
        $('link-out').hidden = false;
        const box = $('qr');
        box.innerHTML = '';
        if (window.QRCode) new window.QRCode(box, { text: r.url, width: 180, height: 180 });
        else box.textContent = r.url;
        $('link-url').onclick = (ev) => { ev.preventDefault(); copy(r.url, 'Link copied'); };
      } catch (e) { showError(e); }
    };
    $('restore-form').onsubmit = async (ev) => {
      ev.preventDefault();
      try {
        await api('POST', '/api/account/restore', { code: ev.target.code.value });
        location.href = '/?linked=1';
      } catch (e) { showError(e); }
    };
  }

  // ---------- delete account page ----------
  const delForm = $('delete-form');
  if (delForm) {
    delForm.onsubmit = async (ev) => {
      ev.preventDefault();
      if (!window.confirm('Delete the account and every mailbox in it? This cannot be undone.')) return;
      try {
        await api('POST', '/api/account/delete', { code: delForm.code.value });
        $('delete-done').hidden = false;
        delForm.hidden = true;
      } catch (e) { showError(e); }
    };
    const thisDevice = $('delete-this');
    if (thisDevice) thisDevice.onclick = async () => {
      if (!window.confirm('Delete the account used by this browser and every mailbox in it?')) return;
      try {
        await api('POST', '/api/account/delete', {});
        $('delete-done').hidden = false;
        delForm.hidden = true;
      } catch (e) { showError(e); }
    };
  }

  // ---------- install (PWA) ----------
  let deferred = null;
  window.addEventListener('beforeinstallprompt', (e) => { e.preventDefault(); deferred = e; });
  const installBtn = $('btn-install');
  if (installBtn) {
    installBtn.onclick = async () => {
      if (deferred) {
        deferred.prompt();
        deferred = null;
      } else {
        $('install-hint').textContent = /iphone|ipad/i.test(navigator.userAgent)
          ? 'On iPhone: tap the Share button, then "Add to Home Screen".'
          : 'Open your browser menu and choose "Install app" or "Add to Home screen".';
      }
    };
  }
  if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
  }
})();
