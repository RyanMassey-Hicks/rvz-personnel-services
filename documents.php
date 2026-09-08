<?php
require __DIR__ . '/includes/bootstrap.php';
require __DIR__ . '/includes/candidate_tabs.php';
require_login();

$user = current_user();
if ($user['role'] === 'recruiter') {
    redirect('/dashboard.php');
}

$docTypes = [
    'id_document' => 'ID Document',
    'passport' => 'Passport',
    'drivers_license' => "Driver's License",
    'qualification' => 'Qualification / Certificate',
    'cover_letter' => 'Cover Letter',
    'other' => 'Other',
];

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'upload') {
        $docType = $_POST['doc_type'] ?? '';
        $label = trim($_POST['label'] ?? '');

        if (!array_key_exists($docType, $docTypes)) {
            $errors[] = 'Please choose a document type.';
        } elseif (empty($_FILES['document']['name'])) {
            $errors[] = 'Please choose a file to upload.';
        } else {
            $file = $_FILES['document'];
            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
            $allowedExt = ['pdf', 'doc', 'docx', 'png', 'jpg', 'jpeg'];

            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errors[] = 'There was a problem uploading your file.';
            } elseif ($file['size'] > MAX_UPLOAD_BYTES) {
                $errors[] = 'File must be smaller than 5MB.';
            } elseif (!in_array($ext, $allowedExt, true)) {
                $errors[] = 'File must be a PDF, Word document, or image (PNG/JPG).';
            } else {
                if (!is_dir(UPLOAD_DIR . 'documents')) {
                    @mkdir(UPLOAD_DIR . 'documents', 0755, true);
                }
                $filename = safe_upload_filename($file['name'], $user['id']);
                move_uploaded_file($file['tmp_name'], UPLOAD_DIR . 'documents/' . $filename);

                db()->prepare(
                    'INSERT INTO candidate_documents (user_id, doc_type, label, file_path, original_name) VALUES (?, ?, ?, ?, ?)'
                )->execute([$user['id'], $docType, $label, 'documents/' . $filename, $file['name']]);
                flash('success', 'Document uploaded.');
                redirect('/documents.php');
            }
        }
    } elseif ($action === 'delete') {
        $docId = (int) ($_POST['doc_id'] ?? 0);
        $stmt = db()->prepare('SELECT file_path FROM candidate_documents WHERE id = ? AND user_id = ?');
        $stmt->execute([$docId, $user['id']]);
        $doc = $stmt->fetch();
        if ($doc) {
            @unlink(UPLOAD_DIR . $doc['file_path']);
            db()->prepare('DELETE FROM candidate_documents WHERE id = ? AND user_id = ?')->execute([$docId, $user['id']]);
            flash('success', 'Document removed.');
        }
        redirect('/documents.php');
    }
}

$stmt = db()->prepare('SELECT * FROM candidate_documents WHERE user_id = ? ORDER BY doc_type, uploaded_at DESC');
$stmt->execute([$user['id']]);
$documents = $stmt->fetchAll();

$pageTitle = 'My Documents';
require __DIR__ . '/includes/header.php';
render_candidate_tabs('documents');
?>
<h2 class="mb-1">My Documents</h2>
<p class="text-muted mb-4">Upload supporting documents once, then attach them to applications or share them with recruiters as needed.</p>

<?php foreach ($errors as $e): ?><div class="alert alert-danger"><?= h($e) ?></div><?php endforeach; ?>

<div class="card mb-4"><div class="card-body">
    <h6 class="mb-3">Upload a document</h6>
    <form method="post" enctype="multipart/form-data" class="row g-2 align-items-end">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="upload">
        <div class="col-md-3">
            <label class="form-label small">Type</label>
            <select name="doc_type" class="form-select" required>
                <option value="">Select...</option>
                <?php foreach ($docTypes as $key => $label): ?>
                    <option value="<?= h($key) ?>"><?= h($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-3">
            <label class="form-label small">Label (optional)</label>
            <input type="text" name="label" class="form-control" placeholder="e.g. BCom Honours">
        </div>
        <div class="col-md-4">
            <label class="form-label small">File</label>
            <input type="file" name="document" class="form-control" accept=".pdf,.doc,.docx,.png,.jpg,.jpeg" required>
        </div>
        <div class="col-md-2">
            <button type="submit" class="btn btn-primary w-100">Upload</button>
        </div>
    </form>
</div></div>

<?php foreach ($docTypes as $type => $label): ?>
    <?php $items = array_filter($documents, fn ($d) => $d['doc_type'] === $type); ?>
    <?php if (!$items) continue; ?>
    <h6 class="mb-2"><?= h($label) ?></h6>
    <?php foreach ($items as $doc): ?>
        <div class="card mb-2 shadow-sm"><div class="card-body d-flex justify-content-between align-items-center gap-2">
            <div>
                <a href="<?= h(UPLOAD_URL . $doc['file_path']) ?>" target="_blank"><?= h($doc['label'] ?: $doc['original_name']) ?></a>
                <div class="text-muted small"><?= h(date('M j, Y', strtotime($doc['uploaded_at']))) ?></div>
            </div>
            <form method="post" onsubmit="return confirm('Remove this document?');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="doc_id" value="<?= (int) $doc['id'] ?>">
                <button type="submit" class="btn btn-sm btn-outline-danger">Remove</button>
            </form>
        </div></div>
    <?php endforeach; ?>
<?php endforeach; ?>

<?php if (!$documents): ?>
    <p class="text-muted">No documents uploaded yet.</p>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
