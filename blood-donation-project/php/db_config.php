<?php
/**
 * Pulse — database connection
 * Uses PDO with prepared statements only — never concatenate user
 * input into a query string.
 */

require_once __DIR__ . '/app_config.php';

$host = PULSE_DB_HOST;
$port = PULSE_DB_PORT;
$db   = PULSE_DB_NAME;
$user = PULSE_DB_USER;
$pass = PULSE_DB_PASSWORD;
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (PDOException $e) {
    http_response_code(500);
    exit('Database connection failed.');
}
