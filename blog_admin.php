<?php
/** Recruiter Blog management — privileged account only. */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/settings_tabs.php';
require_recruiter();

$user = current_user();
if (!is_privileged_recruiter($user)) {
    http_response_code(403);
    die('This page is only available to the privileged account.');
}

function blog_slugify(string $title): string
{
    $slug = strtolower(trim($title));
    $slug = preg_replace('/[^a-z0-9]+/', '-', $slug) ?? '';
    return trim($slug, '-');
}

$editing = null;
if (($_GET['edit'] ?? '') !== '') {
    $stmt = db()->prepare('SELECT * FROM blog_posts WHERE id = ?');
    $stmt->execute([(int) $_GET['edit']]);
    $editing = $stmt->fetch() ?: null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
        db()->prepare('DELETE FROM blog_posts WHERE id = ?')->execute([(int) $_POST['id']]);
        flash('success', 'Article deleted.');
        redirect('/blog_admin.php');
    }

    $title = trim($_POST['title'] ?? '');
    $excerpt = trim($_POST['excerpt'] ?? '');
    $body = trim($_POST['body'] ?? '');
    $isPublished = isset($_POST['is_published']) ? 1 : 0;
    $id = (int) ($_POST['id'] ?? 0);

    if ($title === '' || $body === '') {
        flash('danger', 'Title and body are required.');
        redirect('/blog_admin.php' . ($id ? '?edit=' . $id : ''));
    }

    if ($id) {
        $stmt = db()->prepare(
            'UPDATE blog_posts SET title = ?, excerpt = ?, body = ?, is_published = ? WHERE id = ?'
        );
        $stmt->execute([$title, $excerpt, $body, $isPublished, $id]);
        flash('success', 'Article updated.');
    } else {
        $slug = blog_slugify($title);
        $base = $slug;
        $n = 2;
        while (true) {
            $stmt = db()->prepare('SELECT id FROM blog_posts WHERE slug = ?');
            $stmt->execute([$slug]);
            if (!$stmt->fetch()) break;
            $slug = $base . '-' . $n++;
        }
        $stmt = db()->prepare(
            'INSERT INTO blog_posts (slug, title, excerpt, body, is_published, published_at) VALUES (?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$slug, $title, $excerpt, $body, $isPublished, $isPublished ? date('Y-m-d H:i:s') : null]);
        flash('success', 'Article created.');
    }
    redirect('/blog_admin.php');
}

$posts = db()->query('SELECT id, title, is_published, published_at FROM blog_posts ORDER BY created_at DESC')->fetchAll();

$pageTitle = 'Blog Management';
require __DIR__ . '/includes/header.php';
render_settings_tabs('blog');
?>
<h2 class="mb-4">Recruiter Blog</h2>

<div class="row">
    <div class="col-lg-6 mb-4">
        <h5><?= $editing ? 'Edit Article' : 'New Article' ?></h5>
        <form method="post" class="card"><div class="card-body">
            <?= csrf_field() ?>
            <?php if ($editing): ?><input type="hidden" name="id" value="<?= (int) $editing['id'] ?>"><?php endif; ?>
            <div class="mb-3">
                <label class="form-label">Title</label>
                <input type="text" name="title" class="form-control" required value="<?= h($editing['title'] ?? '') ?>">
            </div>
            <div class="mb-3">
                <label class="form-label">Excerpt (shown on the blog list)</label>
                <textarea name="excerpt" rows="2" class="form-control"><?= h($editing['excerpt'] ?? '') ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label">Body (blank line = new paragraph)</label>
                <textarea name="body" rows="10" class="form-control" required><?= h($editing['body'] ?? '') ?></textarea>
            </div>
            <div class="form-check mb-3">
                <input type="checkbox" name="is_published" id="is_published" class="form-check-input" <?= (!$editing || $editing['is_published']) ? 'checked' : '' ?>>
                <label class="form-check-label" for="is_published">Published</label>
            </div>
            <button type="submit" class="btn btn-primary"><?= $editing ? 'Save Changes' : 'Publish Article' ?></button>
            <?php if ($editing): ?><a href="<?= h(base_url('blog_admin.php')) ?>" class="btn btn-outline-secondary">Cancel</a><?php endif; ?>
        </div></form>
    </div>

    <div class="col-lg-6">
        <h5>All Articles</h5>
        <?php foreach ($posts as $p): ?>
            <div class="card mb-2"><div class="card-body d-flex justify-content-between align-items-center">
                <div>
                    <strong><?= h($p['title']) ?></strong>
                    <?php if (!$p['is_published']): ?><span class="badge bg-secondary ms-1">Draft</span><?php endif; ?>
                    <div class="small text-muted"><?= $p['published_at'] ? h(date('M j, Y', strtotime($p['published_at']))) : 'Not published' ?></div>
                </div>
                <div class="d-flex gap-1">
                    <a href="<?= h(base_url('blog_admin.php?edit=' . $p['id'])) ?>" class="btn btn-sm btn-outline-secondary">Edit</a>
                    <form method="post" onsubmit="return confirm('Delete this article?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $p['id'] ?>">
                        <button type="submit" class="btn btn-sm btn-outline-danger">Delete</button>
                    </form>
                </div>
            </div></div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
