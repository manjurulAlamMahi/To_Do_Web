<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

require_login_api();
require_method('POST');
verify_csrf_token();

$body = get_json_body();
$projectName = isset($body['project_name']) ? clean_input((string)$body['project_name']) : '';
$responsible = isset($body['responsible_person']) ? clean_input((string)$body['responsible_person']) : '';
$comment = isset($body['comment']) ? clean_input((string)$body['comment']) : '';

if ($projectName === '') {
    json_response('error', 'Project name is required.', 422);
}
if (mb_strlen($projectName) > 500) {
    json_response('error', 'Project name must be 500 characters or fewer.', 422);
}
if (mb_strlen($responsible) > 200) {
    json_response('error', 'Responsible person must be 200 characters or fewer.', 422);
}
if (mb_strlen($comment) > 1000) {
    json_response('error', 'Comment must be 1000 characters or fewer.', 422);
}

try {
    $uid = $_SESSION['user_id'];

    $stmt = $pdo->prepare('SELECT COALESCE(MAX(`order`), 0) + 1 AS next_order FROM plan_tasks WHERE user_id = :uid');
    $stmt->execute(['uid' => $uid]);
    $nextOrder = (int)$stmt->fetch()['next_order'];

    $stmt = $pdo->prepare(
        'INSERT INTO plan_tasks (user_id, project_name, responsible_person, comment, completed, `order`, is_fixed)
         VALUES (:uid, :project_name, :responsible_person, :comment, 0, :order, 1)'
    );
    $stmt->execute([
        'uid'                => $uid,
        'project_name'       => $projectName,
        'responsible_person' => $responsible !== '' ? $responsible : null,
        'comment'            => $comment !== '' ? $comment : null,
        'order'              => $nextOrder,
    ]);
    $id = (int)$pdo->lastInsertId();

    json_response('success', [
        'item' => [
            'id'                 => $id,
            'project_name'       => $projectName,
            'responsible_person' => $responsible !== '' ? $responsible : null,
            'comment'            => $comment !== '' ? $comment : null,
            'completed'          => false,
            'order'              => $nextOrder,
            'is_fixed'           => true,
        ],
    ], 201);
} catch (PDOException $e) {
    json_response('error', 'Could not add item.', 500);
}
