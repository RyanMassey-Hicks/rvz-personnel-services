<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/gauge_widget.php';
require_active_recruiter();

$user = current_user();

$skills = trim($_GET['skills'] ?? '');
$location = trim($_GET['location'] ?? '');
$languages = trim($_GET['languages'] ?? '');
$relocateOnly = isset($_GET['relocate_only']);

$candidates = [];
$searched = $skills !== '' || $location !== '' || $languages !== '';

if ($searched) {
    $sql = "SELECT users.id, users.first_name, users.last_name, users.email, candidate_profiles.*
            FROM candidate_profiles
            JOIN users ON users.id = candidate_profiles.user_id
            WHERE users.role = 'candidate'";
    $params = [];
    if ($skills !== '') {
        $sql .= ' AND candidate_profiles.skills LIKE ?';
        $params[] = '%' . $skills . '%';
    }
    if ($location !== '') {
        $sql .= ' AND candidate_profiles.location LIKE ?';
        $params[] = '%' . $location . '%';
    }
    if ($languages !== '') {
        $sql .= ' AND candidate_profiles.languages LIKE ?';
        $params[] = '%' . $languages . '%';
    }
    if ($relocateOnly) {
        $sql .= ' AND candidate_profiles.willing_to_relocate = 1';
    }
    $sql .= ' ORDER BY users.created_at DESC LIMIT 50';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $candidates = $stmt->fetchAll();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_to_pool') {
        $candidateId = (int) ($_POST['candidate_id'] ?? 0);
        $stmt = db()->prepare('SELECT id FROM talent_pool WHERE recruiter_id = ? AND candidate_id = ?');
        $stmt->execute([$user['id'], $candidateId]);
        if (!$stmt->fetch()) {
            db()->prepare('INSERT INTO talent_pool (recruiter_id, candidate_id, notes) VALUES (?, ?, ?)')
                ->execute([$user['id'], $candidateId, trim($_POST['notes'] ?? '')]);
        }
        flash('success', 'Added to your talent pool.');
    } elseif ($action === 'save_search') {
        db()->prepare('INSERT INTO saved_searches (recruiter_id, skills, location, languages, notify) VALUES (?, ?, ?, ?, 1)')
            ->execute([$user['id'], $skills, $location, $languages]);
        flash('success', 'Search saved — you\'ll get an email when new matching CVs come in.');
    }
    redirect('/recruiter_search.php?' . http_build_query(['skills' => $skills, 'location' => $location, 'languages' => $languages]));
}

$stmt = db()->prepare('SELECT candidate_id FROM talent_pool WHERE recruiter_id = ?');
$stmt->execute([$user['id']]);
$poolIds = array_column($stmt->fetchAll(), 'candidate_id');

$pageTitle = 'Direct Search';
require __DIR__ . '/includes/header.php';
?>
<h2 class="mb-1">Direct Search</h2>
<p class="text-muted mb-4">Recruit proactively — search RVZ's candidate database and build your talent pipeline for future planning.</p>

<?php render_candidate_pool_gauges(candidate_pool_stats()); ?>

<form method="get" class="rvz-search-card row g-2 mb-4">
    <div class="col-md-3"><input type="text" name="skills" value="<?= h($skills) ?>" class="form-control" placeholder="Skills (e.g. Python, Sales)"></div>
    <div class="col-md-3"><input type="text" name="location" value="<?= h($location) ?>" class="form-control" placeholder="Location"></div>
    <div class="col-md-3"><input type="text" name="languages" value="<?= h($languages) ?>" class="form-control" placeholder="Languages"></div>
    <div class="col-md-2 d-flex align-items-center">
        <div class="form-check">
            <input type="checkbox" name="relocate_only" id="relocate_only" class="form-check-input" value="1" <?= $relocateOnly ? 'checked' : '' ?>>
            <label class="form-check-label small" for="relocate_only">Willing to relocate</label>
        </div>
    </div>
    <div class="col-md-1"><button class="btn btn-primary w-100" type="submit">Search</button></div>
</form>

<?php if ($searched): ?>
    <form method="post" class="mb-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_search">
        <button type="submit" class="btn btn-outline-primary btn-sm">🔔 Save this search &amp; email me new matching CVs</button>
    </form>

    <p class="text-muted"><?= count($candidates) ?> candidate(s) found.</p>
    <?php foreach ($candidates as $c): ?>
        <div class="card mb-3 shadow-sm"><div class="card-body">
            <div class="d-flex justify-content-between flex-wrap gap-2">
                <div>
                    <h5 class="mb-1"><?= h(trim($c['first_name'] . ' ' . $c['last_name']) ?: 'Candidate #' . $c['id']) ?></h5>
                    <p class="text-muted mb-1"><?= h($c['headline'] ?: 'No headline') ?> &middot; <?= h($c['location'] ?: 'Location not set') ?></p>
                    <p class="mb-1 small"><strong>Skills:</strong> <?= h($c['skills'] ?: '—') ?></p>
                    <?php if (!empty($c['languages'])): ?><p class="mb-1 small"><strong>Languages:</strong> <?= h($c['languages']) ?></p><?php endif; ?>
                    <?php if ($c['resume_path']): ?><a href="<?= h(UPLOAD_URL . $c['resume_path']) ?>" target="_blank" class="small">View resume</a><?php endif; ?>
                </div>
                <div>
                    <?php if (in_array((int) $c['id'], $poolIds, true)): ?>
                        <span class="badge bg-success">In talent pool</span>
                    <?php else: ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add_to_pool">
                            <input type="hidden" name="candidate_id" value="<?= (int) $c['id'] ?>">
                            <button type="submit" class="btn btn-sm btn-outline-primary">+ Add to Talent Pool</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div></div>
    <?php endforeach; ?>
<?php else: ?>
    <p class="text-muted">Enter at least one filter above to search the candidate database.</p>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
