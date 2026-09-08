<?php
/**
 * RVZ admin-only support tool — lets ryan@rvzgroup.co.za set up a Paid
 * company's custom outbound email (their own domain/mailbox) as a
 * white-glove onboarding action. Strictly limited to the exact privileged
 * account, NOT the wider free-access team — regular users (including other
 * free-access teammates) never see or reach this page.
 */
require __DIR__ . '/includes/bootstrap.php';
require_recruiter_with_sla();

$user = current_user();
if (!is_privileged_recruiter($user)) {
    http_response_code(403);
    die('Admin access only.');
}

$companyId = (int) ($_GET['company_id'] ?? $_POST['company_id'] ?? 0);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $companyId = (int) $_POST['company_id'];
    $formType = $_POST['form'] ?? 'smtp';

    if ($formType === 'smtp') {
        $stmt = db()->prepare('SELECT smtp_pass_encrypted FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $existing = $stmt->fetch();
        if (!$existing) {
            $errors[] = 'Company not found.';
        }

        $useCustomSmtp = isset($_POST['use_custom_smtp']) ? 1 : 0;
        $smtpHost = trim($_POST['smtp_host'] ?? '');
        $smtpPort = (int) ($_POST['smtp_port'] ?? 587);
        $smtpSecure = in_array($_POST['smtp_secure'] ?? 'tls', ['tls', 'ssl'], true) ? $_POST['smtp_secure'] : 'tls';
        $smtpUser = trim($_POST['smtp_user'] ?? '');
        $smtpPassInput = $_POST['smtp_pass'] ?? '';
        $smtpFromEmail = trim($_POST['smtp_from_email'] ?? '');
        $smtpFromName = trim($_POST['smtp_from_name'] ?? '');

        if ($useCustomSmtp) {
            if ($smtpHost === '') $errors[] = 'SMTP host is required.';
            if ($smtpFromEmail === '' || !filter_var($smtpFromEmail, FILTER_VALIDATE_EMAIL)) {
                $errors[] = 'A valid "From" email address is required.';
            }
        }

        if (!$errors) {
            // Blank password field = keep whatever's already stored; only overwrite when a new one is typed.
            $passEncrypted = $smtpPassInput !== '' ? encrypt_secret($smtpPassInput) : $existing['smtp_pass_encrypted'];

            db()->prepare(
                'UPDATE companies SET use_custom_smtp = ?, smtp_host = ?, smtp_port = ?, smtp_secure = ?, smtp_user = ?,
                 smtp_pass_encrypted = ?, smtp_from_email = ?, smtp_from_name = ? WHERE id = ?'
            )->execute([
                $useCustomSmtp, $smtpHost, $smtpPort, $smtpSecure, $smtpUser,
                $passEncrypted, $smtpFromEmail, $smtpFromName, $companyId,
            ]);
            flash('success', 'Integration settings saved.');
            redirect('/admin_integrations.php?company_id=' . $companyId);
        }
    } elseif ($formType === 'ai') {
        $stmt = db()->prepare('SELECT ai_image_api_key_encrypted FROM companies WHERE id = ?');
        $stmt->execute([$companyId]);
        $existingAi = $stmt->fetch();
        if (!$existingAi) {
            $errors[] = 'Company not found.';
        }

        $aiProvider = in_array($_POST['ai_image_provider'] ?? 'free', ['free', 'gemini', 'openai'], true)
            ? $_POST['ai_image_provider'] : 'free';
        $aiKeyInput = trim($_POST['ai_image_api_key'] ?? '');
        $aiGuidelines = trim($_POST['ai_brand_guidelines'] ?? '');

        if (in_array($aiProvider, ['gemini', 'openai'], true) && $aiKeyInput === '' && empty($existingAi['ai_image_api_key_encrypted'])) {
            $errors[] = 'An API key is required to use ' . ($aiProvider === 'gemini' ? 'Google Gemini' : 'OpenAI') . '.';
        }

        if (!$errors) {
            // Blank key field = keep whatever's already stored; only overwrite when a new one is typed.
            $keyEncrypted = $aiKeyInput !== '' ? encrypt_secret($aiKeyInput) : $existingAi['ai_image_api_key_encrypted'];

            db()->prepare(
                'UPDATE companies SET ai_image_provider = ?, ai_image_api_key_encrypted = ?, ai_brand_guidelines = ? WHERE id = ?'
            )->execute([$aiProvider, $keyEncrypted, $aiGuidelines, $companyId]);
            flash('success', 'AI ad generation settings saved.');
            redirect('/admin_integrations.php?company_id=' . $companyId);
        }
    }
}

$stmt = db()->prepare('SELECT id, name FROM companies ORDER BY name ASC');
$stmt->execute();
$companies = $stmt->fetchAll();

$company = null;
if ($companyId) {
    $stmt = db()->prepare('SELECT * FROM companies WHERE id = ?');
    $stmt->execute([$companyId]);
    $company = $stmt->fetch();
}

$pageTitle = 'Admin — Integrations';
require __DIR__ . '/includes/header.php';
?>
<h2 class="mb-1">Admin: Company Integrations</h2>
<p class="text-muted mb-4">The control room for per-company integrations — custom outbound email (SMTP) and AI ad
generation. Restricted to <?= h(PRIVILEGED_RECRUITER_EMAIL) ?>.</p>

<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= h($e) ?></div><?php endforeach; ?>

<form method="get" class="row g-2 align-items-end mb-4">
    <div class="col-md-8">
        <label class="form-label small">Company</label>
        <select name="company_id" class="form-select" onchange="this.form.submit()">
            <option value="">— Select a company —</option>
            <?php foreach ($companies as $c): ?>
                <option value="<?= (int) $c['id'] ?>" <?= $companyId === (int) $c['id'] ? 'selected' : '' ?>><?= h($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
</form>

<?php if ($company): ?>
    <div class="card mb-4"><div class="card-body">
        <h5 class="mb-3">Custom SMTP — <?= h($company['name']) ?></h5>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="smtp">
            <input type="hidden" name="company_id" value="<?= (int) $company['id'] ?>">
            <div class="form-check mb-3">
                <input type="checkbox" name="use_custom_smtp" id="useCustomSmtp" class="form-check-input" value="1"
                       <?= $company['use_custom_smtp'] ? 'checked' : '' ?>>
                <label class="form-check-label" for="useCustomSmtp">Send this company's system emails from their own SMTP</label>
            </div>
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label">SMTP Host</label>
                    <input type="text" name="smtp_host" class="form-control" placeholder="mail.theircompany.co.za" value="<?= h($company['smtp_host'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Port</label>
                    <input type="number" name="smtp_port" class="form-control" value="<?= (int) ($company['smtp_port'] ?: 587) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">Encryption</label>
                    <select name="smtp_secure" class="form-select">
                        <option value="tls" <?= ($company['smtp_secure'] ?? 'tls') === 'tls' ? 'selected' : '' ?>>STARTTLS (587)</option>
                        <option value="ssl" <?= ($company['smtp_secure'] ?? '') === 'ssl' ? 'selected' : '' ?>>SSL (465)</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label class="form-label">SMTP Username</label>
                    <input type="text" name="smtp_user" class="form-control" value="<?= h($company['smtp_user'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label">SMTP Password</label>
                    <input type="password" name="smtp_pass" class="form-control"
                           placeholder="<?= !empty($company['smtp_pass_encrypted']) ? 'Already set — leave blank to keep it' : '' ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">"From" email address</label>
                    <input type="email" name="smtp_from_email" class="form-control" placeholder="jobs@theircompany.co.za" value="<?= h($company['smtp_from_email'] ?? '') ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label">"From" display name</label>
                    <input type="text" name="smtp_from_name" class="form-control" placeholder="<?= h($company['name']) ?>" value="<?= h($company['smtp_from_name'] ?? '') ?>">
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-4">Save Integration Settings</button>
        </form>
    </div></div>

    <div class="card"><div class="card-body">
        <h5 class="mb-1">AI Ad Generation — <?= h($company['name']) ?></h5>
        <p class="text-muted small mb-3">Controls how this company's "Create a social ad" button (<code>ads.php</code>) generates images.
        Every company defaults to a free, keyless AI provider — no billing risk. Switch to Gemini or OpenAI here if this
        company wants to bring its own API key (their own cost/account), and add brand guidelines so every generated ad
        follows their brand values automatically.</p>
        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="form" value="ai">
            <input type="hidden" name="company_id" value="<?= (int) $company['id'] ?>">
            <div class="row g-3">
                <div class="col-md-6">
                    <label class="form-label">AI Provider</label>
                    <select name="ai_image_provider" class="form-select">
                        <option value="free" <?= ($company['ai_image_provider'] ?? 'free') === 'free' ? 'selected' : '' ?>>Free AI — no key needed (default)</option>
                        <option value="gemini" <?= ($company['ai_image_provider'] ?? '') === 'gemini' ? 'selected' : '' ?>>Google Gemini — company's own key</option>
                        <option value="openai" <?= ($company['ai_image_provider'] ?? '') === 'openai' ? 'selected' : '' ?>>OpenAI — company's own key</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label">API Key <span class="text-muted small">(only needed for Gemini/OpenAI)</span></label>
                    <input type="password" name="ai_image_api_key" class="form-control"
                           placeholder="<?= !empty($company['ai_image_api_key_encrypted']) ? 'Already set — leave blank to keep it' : 'Paste this company\'s API key' ?>">
                </div>
                <div class="col-12">
                    <label class="form-label">Brand guidelines <span class="text-muted small">(optional — appended to every ad prompt)</span></label>
                    <textarea name="ai_brand_guidelines" class="form-control" rows="3"
                              placeholder="e.g. Always use our navy #0B1F3A and gold #C9A227 palette, a confident and warm tone, and never show competitor logos."><?= h($company['ai_brand_guidelines'] ?? '') ?></textarea>
                </div>
            </div>
            <button type="submit" class="btn btn-primary mt-4">Save AI Settings</button>
        </form>
    </div></div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
