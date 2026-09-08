<?php
/**
 * Opens one PDO connection every script can reuse via db().
 * This host's shared MySQL (host-h.net/cPanel) is standard MySQL/MariaDB,
 * so plain PDO is all we need — note DB_HOST may be an external hostname
 * like sql63.jnb2.host-h.net rather than "localhost" on this account.
 */

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                die('Database connection failed: ' . htmlspecialchars($e->getMessage()));
            }
            die('Sorry, something went wrong connecting to the database. Please try again shortly.');
        }
    }
    return $pdo;
}
