<?php
require_once __DIR__ . '/includes/session.php';

if (is_logged_in()) {
    header('Location: pages/todo-list.php');
} else {
    header('Location: auth/login.php');
}
exit;
