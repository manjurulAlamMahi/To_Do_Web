<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

require_login_api();
require_method('POST');
verify_csrf_token();

$body = get_json_body();
$completed = !empty($body['completed']) ? 1 : 0;

try {
    $stmt = $pdo->prepare('UPDATE plan_tasks SET completed = :completed WHERE user_id = :uid');
    $stmt->execute(['completed' => $completed, 'uid' => $_SESSION['user_id']]);

    json_response('success', ['completed' => (bool)$completed]);
} catch (PDOException $e) {
    json_response('error', 'Could not update items.', 500);
}
