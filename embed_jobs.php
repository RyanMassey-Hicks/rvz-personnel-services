<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/settings_tabs.php';
require_active_recruiter();

$companyId = current_recruiter_company_id();
if (!$companyId) {
    flash('danger', 'Set up your company first.');
    redirect('/become_recruiter.php');
}

$embedScriptTag = '<script src="' . h(base_url('embed/widget.js')) . '" data-target="rvz-jobs" data-company-id="' . $companyId . '" async></script>' . "\n"
    . '<div id="rvz-jobs"></div>';
$embedIframeTag = '<iframe src="' . h(base_url('embed/careers.php?company_id=' . $companyId)) . '" style="width:100%;border:0;min-height:800px;" loading="lazy"></iframe>';
$apiUrl = base_url('api/jobs.php?company_id=' . $companyId);

$pageTitle = 'Embed Your Jobs — Settings';
require __DIR__ . '/includes/header.php';
render_settings_tabs('embed');
?>
<h2 class="mb-1">Add your jobs to your own website</h2>
<p class="text-muted mb-4">Paste one of these snippets into your website's HTML. Both stay live automatically —
new jobs you post here show up there, and every listing links back here so people apply on this site (fully
logged in, resumes and all). Both are already scoped to only your company's jobs.</p>

<div class="mb-4">
    <label class="form-label small text-muted">Option A — JS widget (styles itself into your page)</label>
    <textarea class="form-control font-monospace small" rows="3" readonly onclick="this.select()"><?= h($embedScriptTag) ?></textarea>
</div>
<div class="mb-4">
    <label class="form-label small text-muted">Option B — iframe (a full embedded careers page)</label>
    <textarea class="form-control font-monospace small" rows="2" readonly onclick="this.select()"><?= h($embedIframeTag) ?></textarea>
</div>
<p class="small text-muted">There's also a plain JSON feed at <code><?= h($apiUrl) ?></code> if you'd rather build your own layout.</p>

<?php require __DIR__ . '/includes/footer.php'; ?>
