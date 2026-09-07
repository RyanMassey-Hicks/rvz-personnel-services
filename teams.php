<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/settings_tabs.php';
require_recruiter_with_sla();

$user = current_user();
$companyId = current_recruiter_company_id();
if (!$companyId) {
    flash('danger', 'Set up your company first.');
    redirect('/become_recruiter.php');
}

$stmt = db()->prepare('SELECT name, seat_quantity FROM companies WHERE id = ?');
$stmt->execute([$companyId]);
$company = $stmt->fetch();

$errors = [];
$skipSeatLimit = has_free_recruiter_access($user);
$seatsUsed = company_seats_used($companyId);
$seatQuantity = (int) $company['seat_quantity'];
$companyBilled = company_has_active_billing($companyId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'invite') {
        $email = trim($_POST['email'] ?? '');
        $jobTitle = trim($_POST['job_title'] ?? '');

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        } else {
            $stmt = db()->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$email]);
            $existingUser = $stmt->fetch();
            if ($existingUser) {
                $stmt = db()->prepare('SELECT company_id FROM recruiter_profiles WHERE user_id = ?');
                $stmt->execute([$existingUser['id']]);
                $existingProfile = $stmt->fetch();
                if ($existingProfile && (int) $existingProfile['company_id'] === $companyId) {
                    $errors[] = 'That person is already on your team.';
                }
            }
        }

        if (!$errors) {
            $token = bin2hex(random_bytes(24));
            db()->prepare(
                'INSERT INTO team_invites (company_id, invited_by, email, job_title, token) VALUES (?, ?, ?, ?, ?)'
            )->execute([$companyId, $user['id'], $email, $jobTitle, $token]);

            $inviteUrl = base_url('team_invite.php?token=' . $token);
            send_email(
                $email,
                'You\'re invited to join ' . $company['name'] . ' on ' . SITE_NAME,
                email_wrap(
                    '<p>' . h(trim($user['first_name'] . ' ' . $user['last_name']) ?: $user['username']) . ' has invited you to join '
                    . '<strong>' . h($company['name']) . '</strong>\'s recruiter team on ' . h(SITE_NAME) . '.</p>'
                    . '<p><a href="' . h($inviteUrl) . '">Accept invitation</a></p>'
                    . '<p class="small">You\'ll start on the Free plan (' . FREE_TIER_JOB_LIMIT . ' job posts/month) unless the team\'s paid seats already cover you — check the Pricing page any time for details.</p>'
                )
            );
            flash('success', 'Invitation sent to ' . $email . '.');
            redirect('/teams.php');
        }
    } elseif ($action === 'revoke') {
        $inviteId = (int) ($_POST['invite_id'] ?? 0);
        db()->prepare('UPDATE team_invites SET status = "revoked" WHERE id = ? AND company_id = ? AND status = "pending"')
            ->execute([$inviteId, $companyId]);
        flash('success', 'Invitation revoked.');
        redirect('/teams.php');
    } elseif ($action === 'remove_member') {
        $memberId = (int) ($_POST['user_id'] ?? 0);
        if ($memberId === (int) $user['id']) {
            flash('danger', 'You cannot remove yourself from the team here — cancel your own subscription from Account &amp; Billing instead.');
        } else {
            db()->prepare('UPDATE recruiter_profiles SET company_id = NULL WHERE user_id = ? AND company_id = ?')
                ->execute([$memberId, $companyId]);
            flash('success', 'Team member removed.');
        }
        redirect('/teams.php');
    }
}

$stmt = db()->prepare(
    "SELECT users.id, users.first_name, users.last_name, users.email, recruiter_profiles.job_title,
            subscriptions.status AS sub_status, subscriptions.current_period_end
     FROM recruiter_profiles
     JOIN users ON users.id = recruiter_profiles.user_id
     LEFT JOIN subscriptions ON subscriptions.user_id = users.id
     WHERE recruiter_profiles.company_id = ?
     ORDER BY users.created_at ASC"
);
$stmt->execute([$companyId]);
$members = $stmt->fetchAll();

$stmt = db()->prepare('SELECT * FROM team_invites WHERE company_id = ? AND status = "pending" ORDER BY created_at DESC');
$stmt->execute([$companyId]);
$pendingInvites = $stmt->fetchAll();

$pageTitle = 'Teams — Settings';
require __DIR__ . '/includes/header.php';
render_settings_tabs('teams');
?>
<h2 class="mb-1">Team members</h2>
<p class="text-muted mb-2"><?= h($company['name']) ?> — invite as many team members as you like; anyone beyond your
paid seats starts on the <a href="<?= h(base_url('pricing.php')) ?>">Free plan</a> (<?= FREE_TIER_JOB_LIMIT ?> job posts/month).</p>
<?php if (!$skipSeatLimit): ?>
    <p class="mb-4">
        <?php if ($companyBilled): ?>
            <strong><?= min($seatsUsed, $seatQuantity) ?></strong> of <strong><?= $seatQuantity ?></strong> paid seat<?= $seatQuantity === 1 ? '' : 's' ?> in use
            (<?= $seatsUsed ?> team member<?= $seatsUsed === 1 ? '' : 's' ?>/pending invite<?= $seatsUsed === 1 ? '' : 's' ?> total)
            — billed as one combined amount to the account holder.
        <?php else: ?>
            No paid seats yet — every team member is currently on the Free plan.
        <?php endif; ?>
        <a href="<?= h(base_url('subscribe.php')) ?>">Manage seats</a>
    </p>
<?php else: ?>
    <p class="mb-4 text-muted">Free access is active for this team — everyone has unlimited access.</p>
<?php endif; ?>

<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= h($e) ?></div><?php endforeach; ?>

<div class="card mb-4"><div class="card-body">
    <h6 class="mb-3">Invite a team member</h6>
    <form method="post" class="row g-2 align-items-end">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="invite">
        <div class="col-md-5">
            <label class="form-label small">Email</label>
            <input type="email" name="email" class="form-control" required>
        </div>
        <div class="col-md-5">
            <label class="form-label small">Job title (optional)</label>
            <select name="job_title_select" id="rvzJobTitleSelect" class="form-select">
                <option value="">— None specified —</option>
                <option value="Recruitment Consultant">Recruitment Consultant</option>
                <option value="Talent Acquisition Specialist">Talent Acquisition Specialist</option>
                <option value="Senior Recruiter">Senior Recruiter</option>
                <option value="Recruitment Manager">Recruitment Manager</option>
                <option value="Talent Acquisition Manager">Talent Acquisition Manager</option>
                <option value="HR Manager">HR Manager</option>
                <option value="HR Business Partner">HR Business Partner</option>
                <option value="HR Officer">HR Officer</option>
                <option value="Resourcer / Sourcer">Resourcer / Sourcer</option>
                <option value="Candidate Manager">Candidate Manager</option>
                <option value="Client Relationship Manager">Client Relationship Manager</option>
                <option value="Onboarding Specialist">Onboarding Specialist</option>
                <option value="Recruitment Administrator">Recruitment Administrator</option>
                <option value="Branch Manager">Branch Manager</option>
                <option value="Managing Director">Managing Director</option>
                <option value="__other">Other (specify)&hellip;</option>
            </select>
            <input type="text" name="job_title_custom" id="rvzJobTitleCustom" class="form-control mt-2 d-none"
                   placeholder="Enter custom job title / role">
            <input type="hidden" name="job_title" id="rvzJobTitleFinal">
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Invite</button>
        </div>
    </form>
</div></div>
<script>
(function () {
    var select = document.getElementById('rvzJobTitleSelect');
    var custom = document.getElementById('rvzJobTitleCustom');
    var final = document.getElementById('rvzJobTitleFinal');
    var form = select.closest('form');
    function sync() {
        var isOther = select.value === '__other';
        custom.classList.toggle('d-none', !isOther);
    }
    select.addEventListener('change', sync);
    sync();
    form.addEventListener('submit', function () {
        final.value = select.value === '__other' ? custom.value.trim() : select.value;
    });
})();
</script>

<h6 class="mb-3">Current members (<?= count($members) ?>)</h6>
<?php foreach ($members as $m): ?>
    <div class="card mb-2 shadow-sm"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
        <div>
            <strong><?= h(trim($m['first_name'] . ' ' . $m['last_name']) ?: 'Unnamed') ?></strong>
            <?= (int) $m['id'] === (int) $user['id'] ? '<span class="badge rvz-badge-soft">You</span>' : '' ?>
            <div class="text-muted small"><?= h($m['email']) ?><?= $m['job_title'] ? ' &middot; ' . h($m['job_title']) : '' ?></div>
        </div>
        <div class="d-flex align-items-center gap-2">
            <?php if (has_free_recruiter_access(['id' => $m['id'], 'email' => $m['email']])): ?>
                <span class="badge bg-success">Free access</span>
            <?php elseif (is_within_paid_seats((int) $m['id'], $companyId)): ?>
                <span class="badge bg-success">Paid seat</span>
            <?php elseif ($m['sub_status'] === 'active'): ?>
                <span class="badge bg-success">Active</span>
            <?php else: ?>
                <span class="badge bg-secondary">Free plan</span>
            <?php endif; ?>
            <?php if ((int) $m['id'] !== (int) $user['id']): ?>
                <form method="post" onsubmit="return confirm('Remove this person from the team?');">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="remove_member">
                    <input type="hidden" name="user_id" value="<?= (int) $m['id'] ?>">
                    <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
                </form>
            <?php endif; ?>
        </div>
    </div></div>
<?php endforeach; ?>

<?php if ($pendingInvites): ?>
    <h6 class="mb-3 mt-4">Pending invitations</h6>
    <?php foreach ($pendingInvites as $inv): ?>
        <div class="card mb-2 shadow-sm"><div class="card-body d-flex flex-wrap justify-content-between align-items-center gap-2">
            <div>
                <?= h($inv['email']) ?>
                <div class="text-muted small">Invited <?= h(date('M j, Y', strtotime($inv['created_at']))) ?></div>
            </div>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="revoke">
                <input type="hidden" name="invite_id" value="<?= (int) $inv['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary">Revoke</button>
            </form>
        </div></div>
    <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
