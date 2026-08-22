<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

require_login_api();
require_method('POST');
verify_csrf_token();

$body = get_json_body();
$id = isset($body['id']) ? (int)$body['id'] : 0;
$text = isset($body['text']) ? clean_input((string)$body['text']) : '';

if ($id <= 0) {
    json_response('error', 'Invalid task id.', 422);
}
if ($text === '') {
    json_response('error', 'Task text is required.', 422);
}
if (mb_strlen($text) > 500) {
    json_response('error', 'Task text must be 500 characters or fewer.', 422);
}

try {
    $stmt = $pdo->prepare('UPDATE todos SET text = :text WHERE id = :id AND user_id = :uid');
    $stmt->execute(['text' => $text, 'id' => $id, 'uid' => $_SESSION['user_id']]);

    if ($stmt->rowCount() === 0) {
        json_response('error', 'Task not found.', 404);
    }

    json_response('success', ['id' => $id, 'text' => $text]);
} catch (PDOException $e) {
    json_response('error', 'Could not update task.', 500);
}
