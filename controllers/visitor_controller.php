<?php
// ================================================================
// CONTROLLER: VISITOR dashboard
// CRUD  : my visit passes
// Extras: 1) apply for a student membership upgrade
//         2) book suggestion box
//         3) printable day pass with a unique pass code
// ================================================================

function visitor_controller($conn) {
    $action    = $_GET['action'] ?? 'list';
    $me        = current_user();
    $visitorId = (int)$me['id'];

    $error   = '';
    $editing = null;

    /* ---------------- CREATE (book a visit) ---------------- */
    if ($action === 'add' && is_post()) {
        csrf_check();

        $visitDate = trim($_POST['visit_date'] ?? '');
        $purpose   = trim($_POST['purpose'] ?? '');
        $guests    = trim($_POST['guests'] ?? '');

        if (is_blank($visitDate) || is_blank($purpose) || is_blank($guests)) {
            $error = 'Fill in every field.';
        } elseif (!valid_date($visitDate)) {
            $error = 'Choose a valid visit date.';
        } elseif (strtotime($visitDate) < strtotime(date('Y-m-d'))) {
            $error = 'The visit date cannot be in the past.';
        } elseif (!ctype_digit($guests) || (int)$guests < 1 || (int)$guests > 10) {
            $error = 'Guests must be a whole number between 1 and 10.';
        } elseif (strlen($purpose) > 150) {
            $error = 'Purpose must be shorter than 150 characters.';
        } elseif (pass_exists_on_date($conn, $visitorId, $visitDate)) {
            $error = 'You already have a pass for that day.';
        } else {
            if (add_pass($conn, $visitorId, $visitDate, $purpose, (int)$guests)) {
                log_activity($conn, 'Booked a visit for ' . $visitDate);
                set_flash('success', 'Visit pass booked. Show the pass code at the desk.');
                redirect('index.php?page=visitor');
            }
            $error = 'Could not book the pass.';
        }
    }

    /* ---------------- UPDATE ---------------- */
    if ($action === 'update' && is_post()) {
        csrf_check();

        $id        = (int)($_GET['id'] ?? 0);
        $visitDate = trim($_POST['visit_date'] ?? '');
        $purpose   = trim($_POST['purpose'] ?? '');
        $guests    = trim($_POST['guests'] ?? '');
        $status    = $_POST['status'] ?? 'booked';
        $original  = get_pass($conn, $id, $visitorId);

        $editing = ['id' => $id, 'visit_date' => $visitDate, 'purpose' => $purpose,
                    'guests' => $guests, 'status' => $status];

        // ===== NULL / EMPTY VALIDATION ON UPDATE =====
        if (!$original) {
            set_flash('error', 'That pass does not belong to you.');
            redirect('index.php?page=visitor');
        } elseif (is_blank($visitDate) || is_blank($purpose) || is_blank($guests)) {
            $error = 'No field can be left empty (NULL). All fields are required.';
        } elseif (!valid_date($visitDate)) {
            $error = 'Choose a valid visit date.';
        } elseif (!ctype_digit($guests) || (int)$guests < 1 || (int)$guests > 10) {
            $error = 'Guests must be a whole number between 1 and 10.';
        } elseif (!in_array($status, ['booked', 'cancelled'], true)) {
            $error = 'Choose a valid status.';
        } elseif ($status === 'booked' && pass_exists_on_date($conn, $visitorId, $visitDate, $id)) {
            $error = 'You already have another pass for that day.';
        } else {
            if (update_pass($conn, $id, $visitorId, $visitDate, $purpose, (int)$guests, $status)) {
                log_activity($conn, 'Updated visit pass #' . $id);
                set_flash('success', 'Visit pass updated.');
                redirect('index.php?page=visitor');
            }
            $error = 'Update failed.';
        }
    }

    /* ---------------- READ one row into the form ---------------- */
    if ($action === 'edit' && !$editing) {
        $editing = get_pass($conn, (int)($_GET['id'] ?? 0), $visitorId);
        if (!$editing) {
            set_flash('error', 'That pass does not belong to you.');
            redirect('index.php?page=visitor');
        }
    }

    /* ---------------- DELETE ---------------- */
    if ($action === 'delete') {
        csrf_check();
        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0 && delete_pass($conn, $id, $visitorId)) {
            log_activity($conn, 'Deleted visit pass #' . $id);
            set_flash('success', 'Visit pass deleted.');
        } else {
            set_flash('error', 'Could not delete that pass.');
        }
        redirect('index.php?page=visitor');
    }

    /* -------- FEATURE 1: membership upgrade application -------- */
    if ($action === 'apply' && is_post()) {
        csrf_check();

        $reason  = trim($_POST['reason'] ?? '');
        $current = get_application($conn, $visitorId);

        if ($current && $current['status'] === 'pending') {
            set_flash('error', 'Your application is already waiting for a decision.');
        } elseif (strlen($reason) < 10) {
            set_flash('error', 'Tell us in at least 10 characters why you want to join.');
        } elseif (add_application($conn, $visitorId, $reason)) {
            log_activity($conn, 'Applied for student membership');
            set_flash('success', 'Application sent to the administrator.');
        } else {
            set_flash('error', 'Could not send the application.');
        }
        redirect('index.php?page=visitor');
    }

    /* -------- FEATURE 2: book suggestion box -------- */
    if ($action === 'suggest' && is_post()) {
        csrf_check();

        $title  = trim($_POST['s_title'] ?? '');
        $author = trim($_POST['s_author'] ?? '');
        $reason = trim($_POST['s_reason'] ?? '');

        if (is_blank($title) || is_blank($author)) {
            set_flash('error', 'A suggestion needs both a title and an author.');
        } elseif (strlen($title) > 200 || strlen($author) > 120) {
            set_flash('error', 'Title or author is too long.');
        } elseif (add_suggestion($conn, $visitorId, $title, $author, $reason)) {
            log_activity($conn, 'Suggested the book "' . $title . '"');
            set_flash('success', 'Thanks! Your suggestion was recorded.');
        } else {
            set_flash('error', 'Could not save the suggestion.');
        }
        redirect('index.php?page=visitor');
    }

    if ($action === 'unsuggest') {
        csrf_check();
        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0 && delete_suggestion($conn, $id, $visitorId)) {
            set_flash('success', 'Suggestion removed.');
        } else {
            set_flash('error', 'Could not remove that suggestion.');
        }
        redirect('index.php?page=visitor');
    }

    /* ---------------- Data for the view ---------------- */
    $passes      = get_passes($conn, $visitorId);
    $application = get_application($conn, $visitorId);
    $suggestions = get_suggestions($conn, $visitorId);
    $stats       = get_visitor_stats($conn, $visitorId);

    require __DIR__ . '/../views/visitor/dashboard.php';
}
