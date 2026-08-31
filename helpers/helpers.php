<?php
// ================================================================
// HELPERS - small functions used everywhere (security, output, dates)
// ================================================================

/* ================= Output safety ================= */

// SECURITY: always print user data through esc() to stop XSS.
function esc($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

// Send the browser to another page and stop the script.
function redirect($url) {
    header('Location: ' . $url);
    exit;
}

function is_post() {
    return $_SERVER['REQUEST_METHOD'] === 'POST';
}

/* ================= CSRF protection ================= */
// A CSRF token is a secret value stored in the session and copied into every
// form. A different website cannot read it, so it cannot forge a request.

function csrf_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// Prints the hidden input that every POST form must contain.
function csrf_field() {
    echo '<input type="hidden" name="csrf_token" value="' . csrf_token() . '">';
}

// Adds the token to a link (used by Delete / Issue / Return links).
function csrf_url($url) {
    return $url . '&csrf_token=' . csrf_token();
}

// Stops the request if the token is missing or wrong.
function csrf_check() {
    $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    if (!is_string($token) || !hash_equals(csrf_token(), $token)) {
        http_response_code(403);
        die('Security check failed (invalid CSRF token). Please go back and try again.');
    }
}

/* ================= Login / role guards ================= */

function is_logged_in() {
    return isset($_SESSION['user']);
}

function current_user() {
    return $_SESSION['user'] ?? null;
}

function current_role() {
    return $_SESSION['user']['role'] ?? '';
}

// Logs the user out automatically after SESSION_TIMEOUT seconds of inactivity.
function check_session_timeout() {
    if (!is_logged_in()) {
        return;
    }
    if (isset($_SESSION['last_active']) && (time() - $_SESSION['last_active']) > SESSION_TIMEOUT) {
        $_SESSION = [];
        session_regenerate_id(true);
        set_flash('error', 'Your session expired after 30 minutes. Please log in again.');
        redirect('index.php?page=login');
    }
    $_SESSION['last_active'] = time();
}

// Only lets the matching role through; everybody else is sent away.
function require_role($role) {
    if (!is_logged_in()) {
        set_flash('error', 'Please log in to continue.');
        redirect('index.php?page=login');
    }
    if (current_role() !== $role) {
        redirect('index.php?page=' . current_role());
    }
}

/* ================= Flash messages ================= */
// A flash message is shown once on the next page, then deleted.

function set_flash($type, $message) {
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flash() {
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

/* ================= AJAX / JSON ================= */

function json_out($data, $code = 200) {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

/* ================= Small utilities ================= */

function money($amount) {
    return CURRENCY . number_format((float)$amount, 2);
}

function nice_date($date) {
    return ($date && $date !== '0000-00-00') ? date('d M Y', strtotime($date)) : '-';
}

// Fine = FINE_PER_DAY for every day past the due date.
function calculate_fine($dueDate, $returnDate = null) {
    if (empty($dueDate)) {
        return 0.0;
    }
    $end  = $returnDate ? strtotime($returnDate) : time();
    $days = floor(($end - strtotime($dueDate)) / 86400);
    return $days > 0 ? (float)($days * FINE_PER_DAY) : 0.0;
}

// Negative = days left, positive = days late.
function days_late($dueDate) {
    if (empty($dueDate)) {
        return 0;
    }
    return (int)floor((time() - strtotime($dueDate)) / 86400);
}

function role_label($role) {
    $labels = [
        'admin'     => 'Administrator',
        'librarian' => 'Librarian',
        'student'   => 'Student',
        'visitor'   => 'Visitor',
    ];
    return $labels[$role] ?? ucfirst($role);
}

/* ================= Server-side validation helpers ================= */
// PHP VALIDATION: never trust the browser. The JavaScript checks are only for
// convenience; these functions are the real gate.

function is_blank($value) {
    return trim((string)$value) === '';
}

function valid_email($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

function valid_contact($contact) {
    return preg_match('/^[0-9+\-\s()]{6,20}$/', $contact) === 1;
}

function valid_date($date) {
    $parts = explode('-', $date);
    return count($parts) === 3
        && checkdate((int)$parts[1], (int)$parts[2], (int)$parts[0]);
}
