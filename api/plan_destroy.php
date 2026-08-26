<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

require_login_api();
require_method('POST');
verify_csrf_token();

$body = get_json_body();
$id = isset($body['id']) ? (int)$body['id'] : 0;

if ($id <= 0) {
    json_response('error', 'Invalid item id.', 422);
}

try {
    $stmt = $pdo->prepare('DELETE FROM plan_tasks WHERE id = :id AND user_id = :uid');
    $stmt->execute(['id' => $id, 'uid' => $_SESSION['user_id']]);

    if ($stmt->rowCount() === 0) {
        json_response('error', 'Item not found.', 404);
    }

    json_response('success', ['id' => $id]);
} catch (PDOException $e) {
    json_response('error', 'Could not delete item.', 500);
}
