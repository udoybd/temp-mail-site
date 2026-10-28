<article class="page"><div class="wrap narrow">
<p class="post-meta"><a href="/blog">Guides</a> · <?= $e(date('j M Y', strtotime($post['date']))) ?></p>
<h1><?= $e($post['title']) ?></h1>
<?= $post['body'] ?>
<?= Tms\View::ad($adsense, $adSlot) ?>
<p><a class="btn btn-primary" href="/#inbox">Get a free temporary email</a></p>
</div></article>
