<?php
require __DIR__ . '/includes/bootstrap.php';
require_recruiter_with_sla();

$user = current_user();
$jobId = (int) ($_GET['id'] ?? 0);
$shortlistedOnly = isset($_GET['shortlisted']);

$stmt = db()->prepare('SELECT * FROM jobs WHERE id = ? AND company_id = ?');
$stmt->execute([$jobId, current_recruiter_company_id()]);
$job = $stmt->fetch();
if (!$job) {
    http_response_code(404);
    die('Job not found, or you do not have permission to view its pipeline.');
}

$stmt = db()->prepare(
    'SELECT applications.*, users.username, users.first_name, users.last_name, users.email,
            (SELECT status FROM screening_requests WHERE application_id = applications.id ORDER BY requested_at DESC LIMIT 1) AS screening_status
     FROM applications
     JOIN users ON users.id = applications.candidate_id
     WHERE applications.job_id = ?' . ($shortlistedOnly ? ' AND applications.shortlisted = 1' : '') . '
     ORDER BY applications.applied_on ASC'
);
$stmt->execute([$jobId]);
$applications = $stmt->fetchAll();

$pipelineOrder = ['applied', 'screening', 'interview', 'offer', 'hired'];
$stageLabels = ['applied' => 'Applied', 'screening' => 'Screening', 'interview' => 'Interview', 'offer' => 'Offer', 'hired' => 'Hired'];

$columns = array_fill_keys($pipelineOrder, []);
$rejected = [];
foreach ($applications as $app) {
    if ($app['stage'] === 'rejected') {
        $rejected[] = $app;
    } else {
        $columns[$app['stage']][] = $app;
    }
}

$pageTitle = 'Pipeline — ' . $job['title'];
$extraHead = '<style>
    .candidate-card.is-viewed { border-left: 3px solid var(--rvz-silver-blue, #6f8fb8); }
    .candidate-card.is-contacted { border-left: 3px solid var(--rvz-success, #1f9d55); }
</style>';
require __DIR__ . '/includes/header.php';

function render_candidate_card(array $app): void
{
    ?>
    <div class="candidate-card<?= !empty($app['viewed_by_recruiter']) ? ' is-viewed' : '' ?><?= !empty($app['contacted']) ? ' is-contacted' : '' ?>"
         draggable="true" data-app-id="<?= (int) $app['id'] ?>">
        <strong><?= h(trim($app['first_name'] . ' ' . $app['last_name']) ?: $app['username']) ?></strong>
        <div class="text-muted small"><?= h($app['email']) ?></div>
        <span class="badge bg-light text-dark source-badge">via <?= h(ucfirst($app['source'])) ?></span>
        <?php if ($app['resume_path']): ?>
            <a href="<?= h(UPLOAD_URL . $app['resume_path']) ?>" target="_blank" class="d-block small mt-1">Resume</a>
        <?php endif; ?>
        <div class="rvz-flag-row">
            <label><input type="checkbox" data-rvz-flag="viewed_by_recruiter" data-app-id="<?= (int) $app['id'] ?>" <?= !empty($app['viewed_by_recruiter']) ? 'checked' : '' ?>> Viewed</label>
            <label><input type="checkbox" data-rvz-flag="contacted" data-app-id="<?= (int) $app['id'] ?>" <?= !empty($app['contacted']) ? 'checked' : '' ?>> Contacted</label>
            <label><input type="checkbox" data-rvz-flag="shortlisted" data-app-id="<?= (int) $app['id'] ?>" <?= !empty($app['shortlisted']) ? 'checked' : '' ?>> Shortlisted</label>
        </div>
        <div class="mt-2">
            <?php if ($app['screening_status'] === 'requested'): ?>
                <span class="badge rvz-badge-soft">MIE screening requested</span>
            <?php elseif ($app['screening_status'] === 'completed'): ?>
                <span class="badge bg-success">MIE screening completed</span>
            <?php else: ?>
                <button type="button" class="btn btn-sm btn-outline-secondary" data-rvz-request-screening data-app-id="<?= (int) $app['id'] ?>">Request MIE Screening</button>
            <?php endif; ?>
        </div>
        <?php if ($app['stage'] === 'hired'): ?>
            <div class="mt-2">
                <select class="form-select form-select-sm" data-rvz-placement data-app-id="<?= (int) $app['id'] ?>">
                    <?php foreach (['' => 'Placement status...', 'active' => 'Active placement', 'ended' => 'Placement ended'] as $val => $label): ?>
                        <option value="<?= h($val) ?>" <?= ($app['placement_status'] ?? '') === $val ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        <?php endif; ?>
    </div>
    <?php
}
?>

<div class="d-flex flex-wrap justify-content-between align-items-center mb-2 gap-2">
    <h2 class="mb-0"><?= h($job['title']) ?> — Pipeline</h2>
    <a href="<?= h(base_url('pipeline.php?id=' . $jobId . ($shortlistedOnly ? '' : '&shortlisted=1'))) ?>" class="btn btn-sm btn-outline-primary">
        <?= $shortlistedOnly ? 'Show all candidates' : 'Show shortlisted only' ?>
    </a>
</div>
<p class="text-muted mb-4">Drag a candidate card between columns to move them through the pipeline. Tick Viewed/Contacted/Shortlisted as you work through applications.</p>

<div class="pipeline-board rvz-overflow">
    <?php foreach ($pipelineOrder as $stage): ?>
        <div class="pipeline-column" data-stage="<?= h($stage) ?>">
            <h6><?= h($stageLabels[$stage]) ?> (<?= count($columns[$stage]) ?>)</h6>
            <?php foreach ($columns[$stage] as $app): render_candidate_card($app); endforeach; ?>
        </div>
    <?php endforeach; ?>

    <div class="pipeline-column" data-stage="rejected" style="background:#fff0f0;">
        <h6>Rejected (<?= count($rejected) ?>)</h6>
        <?php foreach ($rejected as $app): render_candidate_card($app); endforeach; ?>
    </div>
</div>

<?php
$extraJs = '<script>
const csrfToken = ' . json_encode(csrf_token()) . ';
let draggedCard = null;

document.querySelectorAll(".candidate-card").forEach(card => {
    card.addEventListener("dragstart", () => {
        draggedCard = card;
        card.classList.add("dragging");
    });
    card.addEventListener("dragend", () => card.classList.remove("dragging"));
});

document.querySelectorAll(".pipeline-column").forEach(column => {
    column.addEventListener("dragover", e => {
        e.preventDefault();
        column.classList.add("drag-over");
    });
    column.addEventListener("dragleave", () => column.classList.remove("drag-over"));
    column.addEventListener("drop", e => {
        e.preventDefault();
        column.classList.remove("drag-over");
        if (!draggedCard) return;

        const appId = draggedCard.dataset.appId;
        const newStage = column.dataset.stage;
        column.appendChild(draggedCard);

        fetch("' . h(base_url('ajax/move_stage.php')) . '", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `application_id=${appId}&stage=${newStage}&csrf_token=${encodeURIComponent(csrfToken)}`,
        }).then(r => r.json()).then(data => {
            if (!data.ok) alert("Could not move candidate: " + data.error);
        });
    });
});

document.querySelectorAll("[data-rvz-placement]").forEach(sel => {
    sel.addEventListener("change", () => {
        fetch("' . h(base_url('ajax/update_placement_status.php')) . '", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `application_id=${sel.dataset.appId}&status=${encodeURIComponent(sel.value)}&csrf_token=${encodeURIComponent(csrfToken)}`,
        }).then(r => r.json()).then(data => { if (!data.ok) alert("Could not update: " + data.error); });
    });
});

document.querySelectorAll("[data-rvz-request-screening]").forEach(btn => {
    btn.addEventListener("click", () => {
        btn.disabled = true;
        fetch("' . h(base_url('ajax/request_screening.php')) . '", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `application_id=${btn.dataset.appId}&csrf_token=${encodeURIComponent(csrfToken)}`,
        }).then(r => r.json()).then(data => {
            if (!data.ok) { alert("Could not request screening: " + data.error); btn.disabled = false; return; }
            btn.outerHTML = "<span class=\\"badge rvz-badge-soft\\">MIE screening requested</span>";
        });
    });
});

document.querySelectorAll("[data-rvz-flag]").forEach(cb => {
    cb.addEventListener("change", () => {
        const appId = cb.dataset.appId;
        const flag = cb.dataset.rvzFlag;
        fetch("' . h(base_url('ajax/update_application_flag.php')) . '", {
            method: "POST",
            headers: { "Content-Type": "application/x-www-form-urlencoded" },
            body: `application_id=${appId}&flag=${flag}&value=${cb.checked ? 1 : 0}&csrf_token=${encodeURIComponent(csrfToken)}`,
        }).then(r => r.json()).then(data => {
            if (!data.ok) { alert("Could not update: " + data.error); cb.checked = !cb.checked; return; }
            const card = cb.closest(".candidate-card");
            if (flag === "viewed_by_recruiter") card.classList.toggle("is-viewed", cb.checked);
            if (flag === "contacted") card.classList.toggle("is-contacted", cb.checked);
        });
    });
});
</script>';
require __DIR__ . '/includes/footer.php';
?>
