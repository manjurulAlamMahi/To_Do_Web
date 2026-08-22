<?php
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../config/db.php';

require_login_api();
require_method('POST');
verify_csrf_token();

$body = get_json_body();
$order = $body['order'] ?? null;

if (!is_array($order) || empty($order)) {
    json_response('error', 'Invalid order payload.', 422);
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare('UPDATE todos SET `order` = :order WHERE id = :id AND user_id = :uid');
    foreach ($order as $index => $id) {
        $stmt->execute([
            'order' => $index + 1,
            'id'    => (int)$id,
            'uid'   => $_SESSION['user_id'],
        ]);
    }

    $pdo->commit();

    json_response('success', ['order' => array_map('intval', $order)]);
} catch (PDOException $e) {
    $pdo->rollBack();
    json_response('error', 'Could not save task order.', 500);
}
