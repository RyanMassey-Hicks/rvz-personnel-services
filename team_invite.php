<?php
require __DIR__ . '/includes/bootstrap.php';

$token = $_GET['token'] ?? $_POST['token'] ?? '';
$stmt = db()->prepare(
    'SELECT team_invites.*, companies.name AS company_name FROM team_invites
     JOIN companies ON companies.id = team_invites.company_id
     WHERE token = ?'
);
$stmt->execute([$token]);
$invite = $stmt->fetch();

if (!$invite) {
    http_response_code(404);
    die('This invitation link is invalid.');
}
if ($invite['status'] !== 'pending') {
    http_response_code(410);
    die('This invitation has already been used or was revoked.');
}

$user = current_user();
$errors = [];

function accept_invite_for_user(array $invite, int $userId): void
{
    db()->prepare('UPDATE users SET role = "recruiter" WHERE id = ?')->execute([$userId]);

    $stmt = db()->prepare('SELECT user_id FROM recruiter_profiles WHERE user_id = ?');
    $stmt->execute([$userId]);
    if ($stmt->fetch()) {
        db()->prepare('UPDATE recruiter_profiles SET company_id = ?, job_title = ? WHERE user_id = ?')
            ->execute([$invite['company_id'], $invite['job_title'], $userId]);
    } else {
        db()->prepare('INSERT INTO recruiter_profiles (user_id, company_id, job_title) VALUES (?, ?, ?)')
            ->execute([$userId, $invite['company_id'], $invite['job_title']]);
    }
    db()->prepare('UPDATE team_invites SET status = "accepted", accepted_at = NOW() WHERE id = ?')->execute([$invite['id']]);
}

if ($user) {
    if (strcasecmp($user['email'], $invite['email']) !== 0) {
        $errors[] = 'You\'re logged in as ' . $user['email'] . ', but this invitation was sent to ' . $invite['email'] . '. Log out and try again with the right account.';
    } elseif ($user['role'] === 'recruiter' && current_recruiter_company_id() && current_recruiter_company_id() !== (int) $invite['company_id']) {
        $errors[] = 'Your account is already part of a different company. Contact support if you need to move teams.';
    } elseif (current_recruiter_company_id() === (int) $invite['company_id']) {
        flash('info', 'You\'re already part of ' . $invite['company_name'] . '.');
        redirect('/dashboard.php');
    } elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$errors) {
        csrf_verify();
        accept_invite_for_user($invite, (int) $user['id']);
        flash('success', 'Welcome to ' . $invite['company_name'] . '!');
        redirect('/dashboard.php');
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $password2 = $_POST['password2'] ?? '';
    $firstName = trim($_POST['first_name'] ?? '');
    $lastName = trim($_POST['last_name'] ?? '');

    if ($username === '' || strlen($username) < 3) $errors[] = 'Username must be at least 3 characters.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $password2) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = ? OR username = ?');
        $stmt->execute([$invite['email'], $username]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with that email or username already exists — please log in first, then open this invite link again.';
        }
    }

    if (!$errors) {
        $stmt = db()->prepare(
            'INSERT INTO users (username, email, password_hash, first_name, last_name, role, signed_up_via) VALUES (?, ?, ?, ?, ?, "recruiter", "website")'
        );
        $stmt->execute([$username, $invite['email'], password_hash($password, PASSWORD_DEFAULT), $firstName, $lastName]);
        $newUserId = (int) db()->lastInsertId();
        accept_invite_for_user($invite, $newUserId);
        log_in_user($newUserId);

        flash('success', 'Welcome to ' . $invite['company_name'] . '!');
        redirect('/dashboard.php');
    }
}

$pageTitle = 'Join ' . $invite['company_name'];
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-md-6">
    <h2 class="mb-1">Join <?= h($invite['company_name']) ?></h2>
    <p class="text-muted mb-4">You've been invited to join this company's recruiter team on <?= h(SITE_NAME) ?>.</p>

    <?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= h($e) ?></div><?php endforeach; ?>

    <?php if ($user): ?>
        <?php if (!$errors): ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= h($token) ?>">
                <button type="submit" class="btn btn-primary">Accept Invitation as <?= h($user['email']) ?></button>
            </form>
        <?php endif; ?>
    <?php else: ?>
        <p class="text-muted small">Create your account with <strong><?= h($invite['email']) ?></strong> to accept.
        Already have an account with this email? <a href="<?= h(base_url('login.php?next=' . urlencode('/team_invite.php?token=' . $token))) ?>">Log in instead</a>.</p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="token" value="<?= h($token) ?>">
            <div class="row">
                <div class="col-md-6 mb-3"><label class="form-label">First name</label><input type="text" name="first_name" class="form-control"></div>
                <div class="col-md-6 mb-3"><label class="form-label">Last name</label><input type="text" name="last_name" class="form-control"></div>
            </div>
            <div class="mb-3"><label class="form-label">Username</label><input type="text" name="username" class="form-control" required></div>
            <div class="mb-3"><label class="form-label">Password</label><input type="password" name="password" class="form-control" required minlength="8"></div>
            <div class="mb-3"><label class="form-label">Confirm password</label><input type="password" name="password2" class="form-control" required minlength="8"></div>
            <button type="submit" class="btn btn-primary w-100">Create Account &amp; Join</button>
        </form>
    <?php endif; ?>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>
