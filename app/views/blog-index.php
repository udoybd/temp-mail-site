<section class="page"><div class="wrap">
<div class="page-head">
  <h1>Blog</h1>
  <p class="muted">Guides and tips on using a temporary email for sign-ups, verification codes and privacy.</p>
</div>
<?php if (!$posts): ?>
  <div class="page-card center"><p class="muted">No posts yet. Check back soon.</p></div>
<?php endif; ?>
<div class="post-grid">
<?php foreach ($posts as $post): ?>
  <a class="post-card" href="/blog/<?= $e($post['slug']) ?>">
    <?php if ($post['cover'] !== ''): ?><img class="post-cover" src="<?= $e($post['cover']) ?>" alt="<?= $e($post['cover_alt'] ?? '') ?>" loading="lazy"><?php else: ?><span class="post-cover post-cover-blank" aria-hidden="true"><svg viewBox="0 0 32 24"><rect width="32" height="24" rx="5" fill="currentColor"/><path d="M6.5 7 16 14l9.5-7" fill="none" stroke="#fff" stroke-width="2.2" stroke-linecap="round"/></svg></span><?php endif; ?>
    <span class="post-body">
      <span class="post-meta"><?= $e(date('j M Y', (int) $post['published_at'])) ?></span>
      <h3><?= $e($post['title']) ?></h3>
      <p><?= $e($post['description']) ?></p>
      <span class="read-more">Read more &rarr;</span>
    </span>
  </a>
<?php endforeach; ?>
</div>
</div></section>
