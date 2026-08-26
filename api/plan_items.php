<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

require_login_api();
require_method('GET');

try {
    $stmt = $pdo->prepare(
        'SELECT id, project_name, responsible_person, comment, completed, `order`, is_fixed, created_at, updated_at
         FROM plan_tasks
         WHERE user_id = :uid
         ORDER BY `order` ASC, id ASC'
    );
    $stmt->execute(['uid' => $_SESSION['user_id']]);
    $rows = $stmt->fetchAll();

    $items = array_map(static function (array $row): array {
        return [
            'id'                 => (int)$row['id'],
            'project_name'       => $row['project_name'],
            'responsible_person' => $row['responsible_person'],
            'comment'            => $row['comment'],
            'completed'          => (bool)$row['completed'],
            'order'              => (int)$row['order'],
            'is_fixed'           => (bool)$row['is_fixed'],
            'created_at'         => $row['created_at'],
            'updated_at'         => $row['updated_at'],
        ];
    }, $rows);

    json_response('success', ['items' => $items]);
} catch (PDOException $e) {
    json_response('error', 'Could not fetch plan/task items.', 500);
}
