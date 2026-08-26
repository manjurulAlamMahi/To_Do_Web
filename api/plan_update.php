<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

require_login_api();
require_method('POST');
verify_csrf_token();

$body = get_json_body();
$id = isset($body['id']) ? (int)$body['id'] : 0;
$projectName = isset($body['project_name']) ? clean_input((string)$body['project_name']) : '';
$responsible = isset($body['responsible_person']) ? clean_input((string)$body['responsible_person']) : '';
$comment = isset($body['comment']) ? clean_input((string)$body['comment']) : '';

if ($id <= 0) {
    json_response('error', 'Invalid item id.', 422);
}
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
    $stmt = $pdo->prepare(
        'UPDATE plan_tasks
         SET project_name = :project_name, responsible_person = :responsible_person, comment = :comment
         WHERE id = :id AND user_id = :uid'
    );
    $stmt->execute([
        'project_name'       => $projectName,
        'responsible_person' => $responsible !== '' ? $responsible : null,
        'comment'            => $comment !== '' ? $comment : null,
        'id'                 => $id,
        'uid'                => $_SESSION['user_id'],
    ]);

    if ($stmt->rowCount() === 0) {
        json_response('error', 'Item not found.', 404);
    }

    json_response('success', [
        'id'                 => $id,
        'project_name'       => $projectName,
        'responsible_person' => $responsible !== '' ? $responsible : null,
        'comment'            => $comment !== '' ? $comment : null,
    ]);
} catch (PDOException $e) {
    json_response('error', 'Could not update item.', 500);
}
