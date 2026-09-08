<?php
/**
 * Every page starts with: require __DIR__ . '/includes/bootstrap.php';
 * This wires up config, the database connection, and helper functions in
 * the right order, so nothing has to remember three separate requires.
 */

$configFile = __DIR__ . '/../config/config.php';
if (!file_exists($configFile)) {
    die('Missing config/config.php — copy config/config.sample.php to config/config.php and fill in your database + OAuth details (see README.md).');
}
require $configFile;
require __DIR__ . '/../config/db.php';
require __DIR__ . '/functions.php';
require __DIR__ . '/rate_limit.php';
require __DIR__ . '/resume_text.php';
require __DIR__ . '/mailer.php';
require __DIR__ . '/ai_image.php';
require __DIR__ . '/gemini_chat.php';
require __DIR__ . '/pdf.php';
require __DIR__ . '/webpush.php';
require __DIR__ . '/trends.php';
expire_stale_listings();
