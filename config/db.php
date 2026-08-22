<?php

/**
 * PDO database connection for the todo_app database.
 * Edit the constants below to match your local MySQL setup.
 */

$db_host = '127.0.0.1';
$db_name = 'thenightowl_taskmanager';
$db_user = 'thenightowl_taskmanager';
$db_pass = 'X1?{kzbrpYA-lBw}';
$db_charset = 'utf8mb4';

$dsn = "mysql:host={$db_host};dbname={$db_name};charset={$db_charset}";

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
} catch (PDOException $e) {
    header('Content-Type: application/json');
    http_response_code(500);
    die(json_encode([
        'status'  => 'error',
        'message' => 'Database connection failed.',
    ]));
}
