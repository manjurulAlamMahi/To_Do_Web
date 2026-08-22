<?php
/**
 * Shared helper functions for API endpoints and pages.
 */

/**
 * Send a standard JSON response and terminate the script.
 *
 * @param string $status  'success' or 'error'
 * @param mixed  $payload data array on success, message string on error
 */
function json_response(string $status, $payload = null, int $httpCode = 200): void
{
    http_response_code($httpCode);
    header('Content-Type: application/json');

    if ($status === 'success') {
        echo json_encode(['status' => 'success', 'data' => $payload]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $payload]);
    }

    exit;
}

/**
 * Validate the CSRF token sent by the client (header X-CSRF-Token or POST field).
 * Responds with a JSON error and stops execution when invalid.
 */
function verify_csrf_token(): void
{
    $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');

    if (!is_string($token) || $token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        json_response('error', 'Invalid or missing CSRF token.', 419);
    }
}

/**
 * Trim and strip tags from user-supplied text.
 */
function clean_input(string $value): string
{
    return trim(strip_tags($value));
}

/**
 * Decode the raw JSON request body into an associative array.
 */
function get_json_body(): array
{
    $raw = file_get_contents('php://input');
    $data = json_decode((string)$raw, true);

    return is_array($data) ? $data : [];
}

/**
 * Require the current request to use the given HTTP method.
 */
function require_method(string $method): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== $method) {
        json_response('error', 'Method not allowed.', 405);
    }
}
