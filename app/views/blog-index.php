<section class="page"><div class="wrap">
<h1>Guides &amp; tips</h1>
<p class="muted">How to use a temporary email for sign-ups, verification codes and privacy.</p>
<div class="grid3">
<?php foreach ($posts as $slug => $post): ?>
  <a class="card card-link" href="/blog/<?= $e($slug) ?>"><h3><?= $e($post['title']) ?></h3><p><?= $e($post['description']) ?></p><span class="post-meta"><?= $e(date('j M Y', strtotime($post['date']))) ?></span></a>
<?php endforeach; ?>
</div>
</div></section>
