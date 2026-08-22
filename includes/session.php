<?php
/**
 * Session bootstrap + auth guards.
 * Include this file first on every page and API endpoint.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Whether a user is currently authenticated.
 */
function is_logged_in(): bool
{
    return !empty($_SESSION['user_id']);
}

/**
 * Guard for HTML pages: redirects to the login page when not authenticated.
 */
function require_login_page(string $redirectTo = '../auth/login.php'): void
{
    if (!is_logged_in()) {
        header('Location: ' . $redirectTo);
        exit;
    }
}

/**
 * Guard for API endpoints: responds with a JSON 401 when not authenticated.
 */
function require_login_api(): void
{
    if (!is_logged_in()) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Not authenticated.']);
        exit;
    }
}
