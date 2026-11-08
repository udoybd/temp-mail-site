(() => {
  'use strict';
  // Tells the server this page is still open (for "Online now" and time on site).
  const ping = () => {
    if (document.hidden) return;
    fetch('/api/ping', { method: 'POST', headers: { 'X-TMS': '1' }, credentials: 'same-origin', keepalive: true }).catch(() => {});
  };
  setTimeout(ping, 3000);
  setInterval(ping, 25000);
  document.addEventListener('visibilitychange', () => { if (!document.hidden) ping(); });
})();
