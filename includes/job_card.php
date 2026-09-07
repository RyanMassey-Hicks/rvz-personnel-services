<?php
/** Shared job-listing card, used on jobs.php, saved_jobs.php, and the candidate home "Recommended" rail. */

function employment_type_labels(): array
{
    return ['full_time' => 'Full-time', 'part_time' => 'Part-time', 'contract' => 'Contract', 'internship' => 'Internship'];
}

const RVZ_HEART_OUTLINE = '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20.5s-7.5-4.6-10-9.3C.4 8 2 4.5 5.5 4.5c2 0 3.5 1.1 4.5 2.7 1-1.6 2.5-2.7 4.5-2.7C18 4.5 19.6 8 18 11.2c-2.5 4.7-10 9.3-10 9.3z"/></svg>';
const RVZ_HEART_FILLED = '<svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor"><path d="M12 20.5s-7.5-4.6-10-9.3C.4 8 2 4.5 5.5 4.5c2 0 3.5 1.1 4.5 2.7 1-1.6 2.5-2.7 4.5-2.7C18 4.5 19.6 8 18 11.2c-2.5 4.7-10 9.3-10 9.3z"/></svg>';

/** A small "share to..." dropdown (WhatsApp/SMS/Facebook/X/LinkedIn/copy link), reused on job cards and the job detail page. */
function render_share_dropdown(int $jobId, string $title, string $uniqueSuffix = ''): void
{
    $jobUrl = base_url('job.php?id=' . $jobId);
    $menuId = 'rvzShareMenu' . $jobId . $uniqueSuffix;
    ?>
    <div class="dropdown rvz-share-dropdown">
        <button type="button" class="rvz-save-btn" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Share this job" title="Share">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="18" cy="5" r="2.5"/><circle cx="6" cy="12" r="2.5"/><circle cx="18" cy="19" r="2.5"/><path d="M8.2 10.7 15.8 6.3M8.2 13.3l7.6 4.4"/></svg>
        </button>
        <ul class="dropdown-menu" id="<?= h($menuId) ?>">
            <li><a class="dropdown-item" target="_blank" rel="noopener" href="<?= h(base_url('share.php?id=' . $jobId . '&platform=whatsapp')) ?>">WhatsApp</a></li>
            <li><a class="dropdown-item" href="<?= h(base_url('share.php?id=' . $jobId . '&platform=sms')) ?>">SMS</a></li>
            <li><a class="dropdown-item" target="_blank" rel="noopener" href="<?= h(base_url('share.php?id=' . $jobId . '&platform=facebook')) ?>">Facebook</a></li>
            <li><a class="dropdown-item" target="_blank" rel="noopener" href="<?= h(base_url('share.php?id=' . $jobId . '&platform=x')) ?>">X (Twitter)</a></li>
            <li><a class="dropdown-item" target="_blank" rel="noopener" href="<?= h(base_url('share.php?id=' . $jobId . '&platform=linkedin')) ?>">LinkedIn</a></li>
            <li><a class="dropdown-item rvz-copy-link" href="#" data-url="<?= h($jobUrl) ?>">Copy Link</a></li>
        </ul>
    </div>
    <?php
}

function render_job_card(array $job, bool $isSaved, bool $showApply, ?string $csrfToken): void
{
    $labels = employment_type_labels();
    ?>
    <div class="card mb-3 shadow-sm rvz-job-card">
        <div class="card-body d-flex gap-3 align-items-start flex-wrap">
            <?php if (!empty($job['company_logo'])): ?>
                <img src="<?= h(UPLOAD_URL . $job['company_logo']) ?>" alt="<?= h($job['company_name']) ?> logo" style="max-height:48px;max-width:48px;object-fit:contain;">
            <?php endif; ?>
            <div class="flex-grow-1">
                <h4><a href="<?= h(base_url('job.php?id=' . $job['id'])) ?>"><?= h($job['title']) ?></a></h4>
                <p class="text-muted mb-1">
                    <?php if (!empty($job['company_id'])): ?>
                        <a href="<?= h(base_url('company_profile.php?id=' . $job['company_id'])) ?>" class="text-muted"><?= h($job['company_name']) ?></a>
                    <?php else: ?>
                        <?= h($job['company_name']) ?>
                    <?php endif; ?>
                    &middot; <?= h($job['location']) ?>
                    <?= $job['is_remote'] ? ' &middot; Remote' : '' ?>
                </p>
                <span class="badge bg-secondary"><?= h($labels[$job['employment_type']] ?? $job['employment_type']) ?></span>
                <?php if (!empty($job['industry_name'])): ?>
                    <span class="badge rvz-badge-soft"><?= h($job['industry_name']) ?></span>
                <?php endif; ?>
                <?php if ($job['salary_min']): ?>
                    <span class="badge bg-success"><?= h(format_zar((float) $job['salary_min'])) ?> - <?= h(format_zar((float) $job['salary_max'])) ?></span>
                <?php endif; ?>
            </div>
            <div class="d-flex flex-column align-items-end gap-2 flex-shrink-0">
                <div class="d-flex gap-2">
                    <?php render_share_dropdown((int) $job['id'], $job['title'], '_card'); ?>
                    <?php if ($csrfToken !== null): ?>
                        <button type="button" class="rvz-save-btn<?= $isSaved ? ' is-saved' : '' ?>" data-job-id="<?= (int) $job['id'] ?>"
                                aria-label="<?= $isSaved ? 'Unsave job' : 'Save job' ?>" title="<?= $isSaved ? 'Saved' : 'Save job' ?>">
                            <?= $isSaved ? RVZ_HEART_FILLED : RVZ_HEART_OUTLINE ?>
                        </button>
                    <?php endif; ?>
                </div>
                <?php if ($showApply): ?>
                    <a href="<?= h(base_url('apply.php?id=' . $job['id'])) ?>" class="btn btn-sm btn-primary">Apply Now</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

function render_save_job_script(string $csrfToken): void
{
    ?>
    <script>
    document.querySelectorAll('.rvz-save-btn[data-job-id]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var jobId = btn.getAttribute('data-job-id');
            fetch('<?= h(base_url('ajax/save_job.php')) ?>', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'job_id=' + encodeURIComponent(jobId) + '&csrf_token=' + encodeURIComponent(<?= json_encode($csrfToken) ?>),
            }).then(function (r) { return r.json(); }).then(function (data) {
                if (!data.ok) { if (data.error) alert(data.error); return; }
                btn.classList.toggle('is-saved', data.saved);
                btn.innerHTML = data.saved
                    ? <?= json_encode(RVZ_HEART_FILLED) ?>
                    : <?= json_encode(RVZ_HEART_OUTLINE) ?>;
            });
        });
    });
    document.querySelectorAll('.rvz-copy-link').forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            var url = link.getAttribute('data-url');
            if (navigator.clipboard) { navigator.clipboard.writeText(url); }
            var original = link.textContent;
            link.textContent = 'Copied!';
            setTimeout(function () { link.textContent = original; }, 1500);
        });
    });
    </script>
    <?php
}
