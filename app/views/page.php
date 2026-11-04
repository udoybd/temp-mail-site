<?php
/** @var array $page @var string $slug @var array $faqs */
?>
<section class="page page-<?= $e($slug) ?>"><div class="wrap<?= in_array($slug, ['features'], true) ? '' : ' narrow' ?>">
<div class="page-head">
  <h1><?= $e($page['title']) ?></h1>
</div>
<div class="page-card prose">
<?= $page['body'] ?>

<?php if ($slug === 'faq'): ?>
  <div class="faq-list">
  <?php foreach ($faqs as $f): ?>
    <details class="faq"><summary><?= $e($f['question']) ?></summary><div class="faq-a"><?= nl2br($e($f['answer'])) ?></div></details>
  <?php endforeach; ?>
  </div>
  <script type="application/ld+json"><?= json_encode([
      '@context' => 'https://schema.org',
      '@type' => 'FAQPage',
      'mainEntity' => array_map(fn ($f) => ['@type' => 'Question', 'name' => $f['question'], 'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['answer']]], $faqs),
  ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
<?php endif; ?>

<?php if ($slug === 'contact'): ?>
  <form id="contact-form" class="contact-form" novalidate>
    <div class="grid-2">
      <label>Your name<input name="name" required maxlength="100" autocomplete="name"></label>
      <label>Your email<input name="email" type="email" required maxlength="200" autocomplete="email"></label>
    </div>
    <label>Subject<input name="subject" maxlength="200"></label>
    <label>Message<textarea name="message" rows="6" required maxlength="5000"></textarea></label>
    <label class="hp" aria-hidden="true">Website<input name="website" tabindex="-1" autocomplete="off"></label>
    <button class="btn btn-primary" type="submit">Send message</button>
  </form>
  <p id="contact-done" class="notice-ok" hidden>Thanks! Your message has been sent. We will reply to your email soon.</p>
<?php endif; ?>
</div>
</div></section>
