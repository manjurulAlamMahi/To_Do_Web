<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

require_login_api();
require_method('POST');
verify_csrf_token();

try {
    $stmt = $pdo->prepare('DELETE FROM plan_tasks WHERE user_id = :uid');
    $stmt->execute(['uid' => $_SESSION['user_id']]);

    json_response('success', ['deleted' => true]);
} catch (PDOException $e) {
    json_response('error', 'Could not clear items.', 500);
}
