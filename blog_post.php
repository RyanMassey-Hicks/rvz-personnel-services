<?php
require __DIR__ . '/includes/bootstrap.php';

$slug = trim($_GET['slug'] ?? '');
$stmt = db()->prepare('SELECT * FROM blog_posts WHERE slug = ? AND is_published = 1');
$stmt->execute([$slug]);
$post = $stmt->fetch();

if (!$post) {
    http_response_code(404);
    $pageTitle = 'Article Not Found — ' . SITE_NAME;
    require __DIR__ . '/includes/header.php';
    echo '<div class="text-center py-5"><h1>Article Not Found</h1><p class="text-muted">This blog post doesn\'t exist or has been removed.</p>'
        . '<a href="' . h(base_url('blog.php')) . '" class="btn btn-primary">Back to Blog</a></div>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = h($post['title']) . ' — ' . SITE_NAME;
$pageDescription = $post['excerpt'] ?: null;
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-lg-8">

<a href="<?= h(base_url('blog.php')) ?>" class="small d-inline-block mb-3">&larr; Back to Blog</a>
<h1 class="mb-1"><?= h($post['title']) ?></h1>
<p class="text-muted mb-4">
    <?= $post['published_at'] ? h(date('F j, Y', strtotime($post['published_at']))) : '' ?>
    &middot; <?= h($post['author_name']) ?>
</p>

<div class="rvz-blog-body">
    <?php foreach (preg_split('/\n\s*\n/', trim($post['body'])) as $para): ?>
        <p><?= nl2br(h(trim($para))) ?></p>
    <?php endforeach; ?>
</div>

<hr class="my-4">
<a href="<?= h(base_url('become_recruiter.php')) ?>" class="btn btn-primary">Post a Job on RVZ</a>

</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
