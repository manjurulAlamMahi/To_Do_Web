<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

require_login_api();
require_method('POST');
verify_csrf_token();

$body = get_json_body();
$text = isset($body['text']) ? clean_input((string)$body['text']) : '';

if ($text === '') {
    json_response('error', 'Task text is required.', 422);
}
if (mb_strlen($text) > 500) {
    json_response('error', 'Task text must be 500 characters or fewer.', 422);
}

try {
    $uid = $_SESSION['user_id'];

    $stmt = $pdo->prepare('SELECT COALESCE(MAX(`order`), 0) + 1 AS next_order FROM todos WHERE user_id = :uid');
    $stmt->execute(['uid' => $uid]);
    $nextOrder = (int)$stmt->fetch()['next_order'];

    $stmt = $pdo->prepare(
        'INSERT INTO todos (user_id, text, completed, `order`, is_fixed)
         VALUES (:uid, :text, 0, :order, 1)'
    );
    $stmt->execute(['uid' => $uid, 'text' => $text, 'order' => $nextOrder]);
    $id = (int)$pdo->lastInsertId();

    json_response('success', [
        'todo' => [
            'id'        => $id,
            'text'      => $text,
            'completed' => false,
            'order'     => $nextOrder,
            'is_fixed'  => true,
        ],
    ], 201);
} catch (PDOException $e) {
    json_response('error', 'Could not add task.', 500);
}
