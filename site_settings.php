<?php
/** Site-wide settings (social links, etc.) — privileged account only, since these are global, not per-recruiter. */
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/settings_tabs.php';
require_recruiter();

$user = current_user();
if (!is_privileged_recruiter($user)) {
    http_response_code(403);
    die('This page is only available to the privileged account.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    foreach (social_platforms() as $key => $label) {
        $url = trim($_POST['social_' . $key] ?? '');
        if ($url !== '' && !is_safe_http_url($url)) {
            flash('danger', h($label) . ' link is not a valid http:// or https:// URL.');
            redirect('/site_settings.php');
        }
        set_site_setting('social_' . $key, $url);
    }
    flash('success', 'Site settings updated.');
    redirect('/site_settings.php');
}

$pageTitle = 'Site Settings';
require __DIR__ . '/includes/header.php';
render_settings_tabs('site');
?>
<h2 class="mb-4">Site Settings</h2>
<p class="text-muted">Footer social media links, shown site-wide.</p>

<form method="post" class="card"><div class="card-body">
    <?= csrf_field() ?>
    <?php foreach (social_platforms() as $key => $label): ?>
        <div class="mb-3">
            <label class="form-label"><?= h($label) ?> URL</label>
            <input type="url" name="social_<?= h($key) ?>" class="form-control" value="<?= h(get_site_setting('social_' . $key)) ?>" placeholder="https://...">
        </div>
    <?php endforeach; ?>
    <button type="submit" class="btn btn-primary">Save Settings</button>
</div></form>

<?php require __DIR__ . '/includes/footer.php'; ?>
