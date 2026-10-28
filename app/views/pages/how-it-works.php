<section class="page"><div class="wrap narrow">
<h1>How <?= $e($site) ?> works</h1>
<p>A temporary email is a real address that receives mail for a short time and then disappears. You get one the second you open the site, with no form to fill in.</p>
<h2>1. Get an address</h2>
<p>When the page loads we create a random address such as <code>quickotter482@<?= $e(Tms\Config::get('domains')[0]) ?></code>. Press <strong>Copy</strong> to put it on your clipboard. Want a different one? Press <strong>New address</strong>, or <strong>Change</strong> to pick your own name.</p>
<h2>2. Use it anywhere</h2>
<p>Paste the address into a sign-up, download or verification form. The site sends its email to us instead of to your real inbox.</p>
<h2>3. Read the email here</h2>
<p>New email shows up in a few seconds without reloading. If the message has a verification code we show it as a button you can tap to copy. Confirmation links get an "Open" button too. You can read the email as HTML or plain text and download attachments.</p>
<h2>How long does it last?</h2>
<ul>
<li>Each email is deleted <strong>3 hours</strong> after it arrives.</li>
<li>A guest mailbox works for <strong>5 days</strong>.</li>
<li>Save a recovery code or link a second device and your mailboxes last <strong>30 days</strong>.</li>
<li>Accounts that are not used for 30 days are deleted.</li>
</ul>
<h2>Use it on your phone too</h2>
<p>Open <strong>Account → Link another device</strong> and scan the QR code with your phone. Both devices now share the same mailboxes. You can also install the site as an app from your browser menu.</p>
<h2>What it is not for</h2>
<p>Do not use a temporary address for accounts you need to keep, such as banking, work or government services. Anyone who guesses an address cannot read your inbox, but the address itself expires, so you could lose access to that account.</p>
<p><a class="btn btn-primary" href="/#inbox">Get my address</a></p>
</div></section>
