<?php
/**
 * Unified sign-in / sign-up — one form, one button. We look up the email on
 * submit: an existing account logs in, a new one is created on the spot as
 * a candidate account (no separate signup.php step needed). signup.php now
 * just redirects here.
 */
require __DIR__ . '/includes/bootstrap.php';

if (is_logged_in()) {
    redirect(post_login_redirect_path(current_user()));
}

$next = $_GET['next'] ?? '';
$errors = [];
$isNewAccount = ($_GET['mode'] ?? '') === 'signup';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = trim($_POST['email'] ?? ''); // "Email or Username" field — see the dual lookup below
    $password = $_POST['password'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $next = $_POST['next'] ?? $next;

    // Returning users can sign in with either their email OR their
    // username — only account CREATION still requires a real email
    // (checked further down, once no existing account matches either).
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? OR username = ?');
    $stmt->execute([$email, $email]);
    $existingUser = $stmt->fetch();

    if ($existingUser) {
        if (!$existingUser['password_hash'] || !password_verify($password, $existingUser['password_hash'])) {
            $errors[] = 'Incorrect email/username or password.';
        } else {
            log_in_user((int) $existingUser['id']);
            redirect($next !== '' ? $next : post_login_redirect_path($existingUser));
        }
    } else {
        $isNewAccount = true;
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Please enter a valid email address.';
        }
        if ($username === '' || strlen($username) < 3) {
            $errors[] = 'Choose a username (at least 3 characters) to create your account.';
        }
        if (strlen($password) < 8) {
            $errors[] = 'Password must be at least 8 characters.';
        }
        if (!$errors) {
            $stmt = db()->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                $errors[] = 'That username is already taken.';
            }
        }
        if (!$errors) {
            $stmt = db()->prepare(
                'INSERT INTO users (username, email, password_hash, role, signed_up_via) VALUES (?, ?, ?, "candidate", "website")'
            );
            $stmt->execute([$username, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $userId = (int) db()->lastInsertId();

            $stmt = db()->prepare('INSERT INTO candidate_profiles (user_id) VALUES (?)');
            $stmt->execute([$userId]);

            log_in_user($userId);
            flash('success', 'Welcome to ' . SITE_NAME . '!');
            redirect($next !== '' ? $next : '/jobs.php');
        }
    }
}

$pageTitle = 'Sign in or sign up — ' . SITE_NAME;
require __DIR__ . '/includes/header.php';
?>

<div class="rvz-auth-backdrop">
  <div class="rvz-auth-blob rvz-auth-blob-1"></div>
  <div class="rvz-auth-blob rvz-auth-blob-2"></div>
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="rvz-auth-card">
        <div class="rvz-auth-mark">
            <img src="<?= h(base_url('assets/img/logo-mark-white.png')) ?>" alt="<?= h(SITE_NAME) ?>" height="26">
        </div>
        <h2 class="mb-1" id="rvzAuthHeading"><?= $isNewAccount ? 'Create your account' : 'Welcome back' ?></h2>
        <p class="text-muted mb-4"><?= $isNewAccount ? 'Takes less than a minute — free for job seekers, always.' : 'One account, one simple step to sign in.' ?></p>

        <div class="d-grid gap-2 mb-4">
          <a href="<?= h(base_url('oauth/google.php')) ?>" class="btn btn-outline-dark rvz-oauth-btn">
            <svg width="18" height="18" viewBox="0 0 24 24"><path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92a5.06 5.06 0 0 1-2.2 3.32v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.1z"/><path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.99.66-2.25 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.85A11 11 0 0 0 12 23z"/><path fill="#FBBC05" d="M5.84 14.1a6.6 6.6 0 0 1 0-4.2V7.05H2.18a11 11 0 0 0 0 9.9l3.66-2.85z"/><path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1a11 11 0 0 0-9.82 6.05l3.66 2.85C6.71 7.3 9.14 5.38 12 5.38z"/></svg>
            Continue with Google
          </a>
          <a href="<?= h(base_url('oauth/linkedin.php')) ?>" class="btn btn-outline-primary rvz-oauth-btn">Continue with LinkedIn</a>
          <a href="<?= h(base_url('oauth/facebook.php')) ?>" class="btn btn-outline-primary rvz-oauth-btn">Continue with Facebook</a>
        </div>

        <div class="text-center text-muted mb-3 rvz-auth-divider"><span>or continue with email</span></div>

        <?php foreach ($errors as $e): ?>
            <div class="alert alert-danger"><?= h($e) ?></div>
        <?php endforeach; ?>

        <form method="post" id="rvzAuthForm">
            <?= csrf_field() ?>
            <input type="hidden" name="next" value="<?= h($next) ?>">
            <div class="mb-3 rvz-field">
                <label class="form-label">Email or Username</label>
                <input type="text" name="email" id="rvzAuthEmail" class="form-control" required autofocus
                       autocomplete="username" placeholder="you@example.com or username"
                       value="<?= h($_POST['email'] ?? '') ?>">
            </div>
            <div class="mb-3 rvz-field rvz-username-field <?= $isNewAccount ? '' : 'rvz-field-collapsed' ?>" id="rvzUsernameField">
                <label class="form-label">Choose a Username <span class="text-muted small">(new here? this creates your account)</span></label>
                <input type="text" name="username" id="rvzAuthUsername" class="form-control" value="<?= h($_POST['username'] ?? '') ?>">
            </div>
            <div class="mb-3 rvz-field">
                <label class="form-label">Password</label>
                <input type="password" name="password" id="rvzAuthPassword" class="form-control" required minlength="8" autocomplete="current-password">
                <div class="rvz-strength-bar mt-2" id="rvzStrengthBar"><span></span></div>
            </div>
            <button class="btn btn-primary w-100 rvz-auth-submit" type="submit" id="rvzAuthSubmit">
                <span class="rvz-btn-label"><?= $isNewAccount ? 'Create Account' : 'Continue' ?></span>
            </button>
        </form>

        <p class="mt-3 text-center" id="rvzSignupPrompt" style="<?= $isNewAccount ? 'display:none;' : '' ?>">
          New here? <a href="<?= h(base_url('login.php?mode=signup')) ?>" id="rvzSignupLink">Create an account</a>
        </p>

        <p class="mt-3 text-center small text-muted">
          Recruiters: sign in the same way above, then click "I'm hiring" in the menu to set up your company workspace.
        </p>
      </div>
    </div>
  </div>
</div>

<style>
.rvz-oauth-btn { display: flex; align-items: center; justify-content: center; gap: .5rem; transition: transform .15s ease; }
.rvz-oauth-btn:hover { transform: translateY(-1px); }
.rvz-auth-divider { position: relative; }
.rvz-auth-divider span { background: var(--bs-body-bg, #fff); padding: 0 .6rem; position: relative; z-index: 1; }
.rvz-auth-divider::before { content: ''; position: absolute; left: 0; right: 0; top: 50%; height: 1px; background: #dee2e6; }
.rvz-field { transition: max-height .3s ease, opacity .3s ease, margin .3s ease; max-height: 6rem; opacity: 1; overflow: hidden; }
.rvz-field-collapsed { max-height: 0; opacity: 0; margin-bottom: 0 !important; pointer-events: none; }
.rvz-strength-bar { height: 4px; border-radius: 2px; background: #e9ecef; overflow: hidden; }
.rvz-strength-bar span { display: block; height: 100%; width: 0%; background: #dc3545; transition: width .25s ease, background-color .25s ease; }
.rvz-auth-submit[disabled] { opacity: .75; }
</style>

<script>
(function () {
    var usernameField = document.getElementById('rvzUsernameField');
    var usernameInput = document.getElementById('rvzAuthUsername');
    var emailInput = document.getElementById('rvzAuthEmail');
    var passwordInput = document.getElementById('rvzAuthPassword');
    var form = document.getElementById('rvzAuthForm');
    var submitBtn = document.getElementById('rvzAuthSubmit');
    var strengthBar = document.querySelector('#rvzStrengthBar span');
    var heading = document.getElementById('rvzAuthHeading');
    var signupPrompt = document.getElementById('rvzSignupPrompt');
    var signupLink = document.getElementById('rvzSignupLink');

    function enterSignupMode() {
        usernameField.classList.remove('rvz-field-collapsed');
        heading.textContent = 'Create your account';
        submitBtn.querySelector('.rvz-btn-label').textContent = 'Create Account';
        signupPrompt.style.display = 'none';
    }

    // The username field stays collapsed for login — it only appears once we
    // actually know this is a new account: either the server told us so after
    // a real submission (no existing user matched, $isNewAccount server-side),
    // or the visitor explicitly clicked "Create an account" below. It used to
    // also auto-reveal on any email-shaped input, but that showed the
    // username field for RETURNING users typing their own email too, which
    // defeats the point of a dual email/username login field — removed.

    // Clicking "Sign up" switches into signup mode instantly, no page reload —
    // the link's href still points to ?mode=signup as a working no-JS fallback.
    if (signupLink) {
        signupLink.addEventListener('click', function (e) {
            e.preventDefault();
            enterSignupMode();
            usernameInput.focus();
        });
    }

    passwordInput.addEventListener('input', function () {
        var val = passwordInput.value;
        var score = 0;
        if (val.length >= 8) score++;
        if (val.length >= 12) score++;
        if (/[A-Z]/.test(val) && /[a-z]/.test(val)) score++;
        if (/[0-9]/.test(val) && /[^A-Za-z0-9]/.test(val)) score++;
        var pct = [0, 25, 50, 75, 100][score];
        var colors = ['#dc3545', '#dc3545', '#fd7e14', '#ffc107', '#198754'];
        strengthBar.style.width = pct + '%';
        strengthBar.style.backgroundColor = colors[score];
    });

    emailInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            (usernameField.classList.contains('rvz-field-collapsed') ? passwordInput : usernameInput).focus();
        }
    });

    form.addEventListener('submit', function () {
        submitBtn.disabled = true;
        submitBtn.querySelector('.rvz-btn-label').textContent = 'One moment…';
    });
})();
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
