<article class="page"><div class="wrap narrow">
<p class="post-meta"><a href="/blog">Blog</a> · <?= $e(date('j M Y', (int) $post['published_at'])) ?></p>
<h1><?= $e($post['title']) ?></h1>
<?php if ($post['cover'] !== ''): ?><img class="post-hero" src="<?= $e($post['cover']) ?>" alt=""><?php endif; ?>
<div class="page-card prose">
<?= $post['body'] ?>
<?= Tms\View::ad($adsense, $adSlot) ?>
<p><a class="btn btn-primary" href="/#inbox">Get a free temporary email</a></p>
</div>
<?php if ($more): ?>
  <h2 class="more-title">More from the blog</h2>
  <div class="post-grid post-grid-sm">
  <?php foreach (array_slice($more, 0, 3) as $p): ?>
    <a class="post-card" href="/blog/<?= $e($p['slug']) ?>"><span class="post-body"><span class="post-meta"><?= $e(date('j M Y', (int) $p['published_at'])) ?></span><h3><?= $e($p['title']) ?></h3><p><?= $e($p['description']) ?></p></span></a>
  <?php endforeach; ?>
  </div>
<?php endif; ?>
</div></article>
<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org', '@type' => 'BlogPosting', 'headline' => $post['title'], 'description' => $post['description'],
    'datePublished' => gmdate('c', (int) $post['published_at']), 'dateModified' => gmdate('c', (int) $post['updated_at']),
    'image' => $post['cover'] !== '' ? $siteUrl . $post['cover'] : $siteUrl . '/assets/og.png',
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?></script>
