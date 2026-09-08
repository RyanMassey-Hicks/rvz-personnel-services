<?php
require __DIR__ . '/../includes/bootstrap.php';

header('Content-Type: application/json');

if (!is_logged_in()) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Please sign in.']);
    exit;
}

$sent = $_POST['csrf_token'] ?? '';
if (!$sent || !hash_equals($_SESSION['csrf_token'] ?? '', $sent)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'error' => 'Invalid security token, please reload the page.']);
    exit;
}

$user = current_user();
$action = $_POST['action'] ?? '';

if ($action === 'mark_read') {
    $id = (int) ($_POST['id'] ?? 0);
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?')->execute([$id, $user['id']]);
    echo json_encode(['ok' => true, 'unread' => unread_notification_count((int) $user['id'])]);
    exit;
}

if ($action === 'mark_all_read') {
    db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ? AND is_read = 0')->execute([$user['id']]);
    echo json_encode(['ok' => true, 'unread' => 0]);
    exit;
}

/**
 * Polled every few seconds by the notification bell (any page, since it's
 * rendered site-wide in header.php) to pick up notifications created since
 * the client last checked — this is the ONE place that needs to know about
 * a new notification; every current and future feature that calls
 * create_notification() is automatically covered here, live, without any
 * per-feature wiring. Drives both the in-app dropdown update and the native
 * browser notification popup.
 */
if ($action === 'poll') {
    $sinceId = (int) ($_GET['since_id'] ?? $_POST['since_id'] ?? 0);
    $stmt = db()->prepare('SELECT * FROM notifications WHERE user_id = ? AND id > ? ORDER BY created_at ASC LIMIT 20');
    $stmt->execute([$user['id'], $sinceId]);
    $new = $stmt->fetchAll();

    $latestId = $sinceId;
    $newOut = array_map(function ($n) use (&$latestId) {
        $latestId = max($latestId, (int) $n['id']);
        return [
            // Stored links are already absolute (e.g. base_url('my_applications.php')
            // baked in at create_notification() call time) — used as-is, same as
            // the server-rendered dropdown's <a href> already does.
            'id' => (int) $n['id'],
            'title' => $n['title'],
            'body' => $n['body'],
            'link' => $n['link'] ?: '',
            'created_at' => $n['created_at'],
        ];
    }, $new);

    echo json_encode([
        'ok' => true,
        'unread' => unread_notification_count((int) $user['id']),
        'new' => $newOut,
        'latest_id' => $latestId,
    ]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false, 'error' => 'Unknown action.']);
