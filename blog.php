<?php
require __DIR__ . '/includes/bootstrap.php';

$stmt = db()->query(
    "SELECT id, slug, title, excerpt, author_name, published_at FROM blog_posts
     WHERE is_published = 1 ORDER BY published_at DESC, id DESC"
);
$posts = $stmt->fetchAll();

$pageTitle = 'Recruiter Blog — ' . SITE_NAME;
$pageDescription = 'Practical hiring and recruitment advice from RVZ Personnel Services & Labour Hiring Specialists.';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-9">

<h1 class="mb-1">Recruiter Blog</h1>
<p class="text-muted mb-4">Practical hiring advice from RVZ Personnel Services</p>

<?php if (!$posts): ?>
    <p class="text-muted">No articles published yet — check back soon.</p>
<?php else: ?>
    <?php foreach ($posts as $post): ?>
        <div class="card mb-3"><div class="card-body">
            <h4 class="mb-1"><a href="<?= h(base_url('blog_post.php?slug=' . urlencode($post['slug']))) ?>" class="text-decoration-none"><?= h($post['title']) ?></a></h4>
            <p class="text-muted small mb-2">
                <?= $post['published_at'] ? h(date('F j, Y', strtotime($post['published_at']))) : '' ?>
                &middot; <?= h($post['author_name']) ?>
            </p>
            <p class="mb-2"><?= h($post['excerpt']) ?></p>
            <a href="<?= h(base_url('blog_post.php?slug=' . urlencode($post['slug']))) ?>">Read more &rarr;</a>
        </div></div>
    <?php endforeach; ?>
<?php endif; ?>

</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
