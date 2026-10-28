// Cloudflare Email Worker for extra mail domains (free plan).
// Email Routing: catch-all -> "Send to a Worker" -> this worker.
// Set two variables on the worker: SITE_URL (https://jamaluddin.info) and INGEST_SECRET
// (copy "ingest_secret" from storage/config.php on the server).
// The worker does no parsing so it stays within the free plan's 10 ms CPU limit.
export default {
  async email(message, env) {
    const res = await fetch(env.SITE_URL.replace(/\/$/, '') + '/api/incoming', {
      method: 'POST',
      headers: {
        'Content-Type': 'message/rfc822',
        'X-Ingest-Secret': env.INGEST_SECRET,
        'X-Rcpt-To': message.to,
      },
      body: message.raw,
    });
    if (res.status >= 500) {
      // Ask the sender's server to retry later instead of losing the mail.
      message.setReject('Temporary failure, try again later');
    }
  },
};
