<?php
/**
 * Sign-up is now handled by the unified login.php flow (one form creates an
 * account automatically if the email doesn't exist yet) — this stays in
 * place only so existing links/bookmarks to /signup.php keep working.
 */
require __DIR__ . '/includes/bootstrap.php';
redirect('/login.php' . (isset($_GET['next']) ? '?next=' . urlencode($_GET['next']) : ''));
