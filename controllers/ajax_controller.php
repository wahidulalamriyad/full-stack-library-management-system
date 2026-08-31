<?php
// ================================================================
// CONTROLLER: AJAX / JSON endpoints
// The dashboards call these with fetch() and redraw a table without
// reloading the page.  Every endpoint checks the role first.
// ================================================================

function ajax_controller($conn) {
    $action = $_GET['action'] ?? '';
    $term   = trim($_GET['q'] ?? '');

    /* ---- Public endpoint: live "is this username free?" on the signup page ---- */
    if ($action === 'check_username') {
        $username = trim($_GET['username'] ?? '');
        if (!preg_match('/^[A-Za-z0-9_]{4,20}$/', $username)) {
            json_out(['ok' => false, 'message' => 'Use 4-20 letters, numbers or underscores.']);
        }
        json_out(username_exists($conn, $username)
            ? ['ok' => false, 'message' => 'That username is taken.']
            : ['ok' => true,  'message' => 'That username is free.']);
    }

    /* ---- Everything below needs a logged-in user ---- */
    if (!is_logged_in()) {
        json_out(['error' => 'Please sign in first.'], 401);
    }

    $user = current_user();
    $role = $user['role'];
    $id   = (int)$user['id'];

    switch ($action) {

        /* ----------- Admin ----------- */
        case 'search_users':
            if ($role !== 'admin') break;
            $roleFilter = $_GET['role'] ?? '';
            if (!in_array($roleFilter, ['admin', 'librarian', 'student', 'visitor'], true)) {
                $roleFilter = '';
            }
            json_out($term === ''
                ? get_users($conn, $roleFilter)
                : search_users($conn, $term, $roleFilter));

        case 'stats':                       // live statistics panel
            if ($role !== 'admin') break;
            json_out(get_system_stats($conn));

        case 'search_logs':
            if ($role !== 'admin') break;
            json_out($term === '' ? get_logs($conn, 12) : search_logs($conn, $term, 50));

        /* ----------- Librarian ----------- */
        case 'search_books':
            if ($role !== 'librarian' && $role !== 'student') break;  // both may browse
            json_out($term === '' ? get_books($conn) : search_books($conn, $term));

        case 'low_stock':
            if ($role !== 'librarian') break;
            json_out(get_low_stock_books($conn));

        case 'search_issues':
            if ($role !== 'librarian') break;
            json_out($term === '' ? get_all_requests($conn) : search_all_requests($conn, $term));

        /* ----------- Student ----------- */
        case 'search_requests':
            if ($role !== 'student') break;
            json_out($term === ''
                ? get_student_requests($conn, $id)
                : search_student_requests($conn, $id, $term));

        /* ----------- Visitor ----------- */
        case 'search_passes':
            if ($role !== 'visitor') break;
            json_out($term === '' ? get_passes($conn, $id) : search_passes($conn, $id, $term));
    }

    // Wrong role, or an action that does not exist.
    json_out(['error' => 'You are not allowed to use this endpoint.'], 403);
}
