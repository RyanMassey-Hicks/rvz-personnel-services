<?php
/**
 * A narrow, secret-key-protected HTTPS API for the scheduled auto-review
 * agent (see MAINTENANCE_API_KEY in config.php). This exists because raw
 * FTP isn't reachable from that agent's sandboxed network (HTTPS-only
 * egress) — everything it needs (reading the error log, reading/patching a
 * specific implicated file, sending the daily report) goes through here
 * instead, over plain HTTPS, with server-side guardrails that don't rely on
 * the calling agent's own judgment alone.
 *
 * This is intentionally NOT a general file-manager API: reads/writes are
 * restricted to the app root, several sensitive paths are hard-blocked
 * below regardless of what's requested, writes require the target file to
 * already exist (no new files), and every write is backed up first and
 * appended to an audit log outside the web root's reach.
 */
require __DIR__ . '/includes/bootstrap.php';

header('Content-Type: text/plain; charset=utf-8');

$key = $_GET['key'] ?? $_POST['key'] ?? '';
if (!defined('MAINTENANCE_API_KEY') || MAINTENANCE_API_KEY === '' || !hash_equals(MAINTENANCE_API_KEY, $key)) {
    http_response_code(403);
    die('forbidden');
}

$appRoot = realpath(__DIR__);
$action = $_GET['action'] ?? $_POST['action'] ?? '';

/** Resolves a caller-supplied relative path safely inside $appRoot, or null if it escapes/doesn't exist. */
function maint_resolve_path(string $appRoot, string $relative): ?string
{
    $relative = ltrim(str_replace('\\', '/', $relative), '/');
    $target = $appRoot . '/' . $relative;
    $real = realpath($target);
    if ($real === false || strpos($real, $appRoot . DIRECTORY_SEPARATOR) !== 0) {
        return null;
    }
    return $real;
}

/** Hard-blocked path prefixes/names — these never get read or written via this API, no exceptions. */
function maint_is_blocked(string $relative): bool
{
    $relative = ltrim(str_replace('\\', '/', $relative), '/');
    $blockedPrefixes = ['config/', 'paystack/'];
    foreach ($blockedPrefixes as $p) {
        if (strpos($relative, $p) === 0) {
            return true;
        }
    }
    $blockedExact = ['includes/functions.php', 'maintenance_api.php'];
    return in_array($relative, $blockedExact, true) || basename($relative) === '.htaccess';
}

function maint_audit(string $line): void
{
    @file_put_contents(
        __DIR__ . '/config/maintenance_audit.log',
        '[' . date('Y-m-d H:i:s') . '] ' . $line . "\n",
        FILE_APPEND
    );
}

switch ($action) {
    case 'read_log':
        $path = __DIR__ . '/config/php_errors.log';
        echo is_file($path) ? file_get_contents($path) : '';
        break;

    case 'clear_log':
        file_put_contents(__DIR__ . '/config/php_errors.log', '');
        maint_audit('cleared php_errors.log');
        echo 'ok';
        break;

    case 'read_file':
        $rel = $_GET['path'] ?? '';
        if ($rel === '' || maint_is_blocked($rel)) {
            http_response_code(403);
            die('blocked');
        }
        $real = maint_resolve_path($appRoot, $rel);
        if (!$real || !is_file($real)) {
            http_response_code(404);
            die('not found');
        }
        echo file_get_contents($real);
        break;

    case 'write_file':
        $rel = $_POST['path'] ?? '';
        $content = $_POST['content'] ?? null;
        if ($rel === '' || $content === null || maint_is_blocked($rel)) {
            http_response_code(403);
            die('blocked');
        }
        $real = maint_resolve_path($appRoot, $rel);
        if (!$real || !is_file($real)) {
            http_response_code(404);
            die('not found — writes may only replace an existing file, never create a new one');
        }
        $backupDir = __DIR__ . '/config/maintenance_backups';
        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }
        $backupName = str_replace('/', '__', ltrim($rel, '/')) . '.' . date('Ymd_His') . '.bak';
        copy($real, $backupDir . '/' . $backupName);

        $oldSize = filesize($real);
        file_put_contents($real, $content);
        clearstatcache(true, $real);
        maint_audit(sprintf('wrote %s (old %d bytes -> new %d bytes, backup: %s)', $rel, $oldSize, strlen($content), $backupName));
        echo 'ok';
        break;

    case 'send_report':
        $subject = $_POST['subject'] ?? 'RVZ site auto-review report';
        $bodyHtml = $_POST['body_html'] ?? '';
        $sent = send_email(PRIVILEGED_RECRUITER_EMAIL, $subject, email_wrap($bodyHtml));
        maint_audit('sent report email: ' . $subject . ' (send_email returned ' . ($sent ? 'true' : 'false — check SMTP config') . ')');
        echo 'ok';
        break;

    default:
        http_response_code(400);
        echo 'unknown action';
}
