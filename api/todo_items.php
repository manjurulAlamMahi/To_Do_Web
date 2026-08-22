<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

require_login_api();
require_method('GET');

try {
    $stmt = $pdo->prepare(
        'SELECT id, text, completed, `order`, is_fixed, created_at, updated_at
         FROM todos
         WHERE user_id = :uid
         ORDER BY `order` ASC, id ASC'
    );
    $stmt->execute(['uid' => $_SESSION['user_id']]);
    $rows = $stmt->fetchAll();

    $todos = array_map(static function (array $row): array {
        return [
            'id'         => (int)$row['id'],
            'text'       => $row['text'],
            'completed'  => (bool)$row['completed'],
            'order'      => (int)$row['order'],
            'is_fixed'   => (bool)$row['is_fixed'],
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }, $rows);

    json_response('success', ['todos' => $todos]);
} catch (PDOException $e) {
    json_response('error', 'Could not fetch tasks.', 500);
}
