<?php
// ================================================================
// CONTROLLER: STUDENT dashboard
// CRUD  : my borrow requests
// Extras: 1) catalogue browser with live availability
//         2) due-date and fine tracker
//         3) printable digital library card
// ================================================================

function student_controller($conn) {
    $action    = $_GET['action'] ?? 'list';
    $me        = current_user();
    $studentId = (int)$me['id'];

    $error   = '';
    $editing = null;

    /* ---------------- CREATE (request a book) ---------------- */
    if ($action === 'add' && is_post()) {
        csrf_check();

        $bookId     = (int)($_POST['book_id'] ?? 0);
        $pickupDate = trim($_POST['pickup_date'] ?? '');
        $notes      = trim($_POST['notes'] ?? '');
        $book       = get_book($conn, $bookId);

        if ($bookId <= 0 || !$book) {
            $error = 'Choose a book from the list.';
        } elseif (is_blank($pickupDate) || !valid_date($pickupDate)) {
            $error = 'Choose a valid pick-up date.';
        } elseif (strtotime($pickupDate) < strtotime(date('Y-m-d'))) {
            $error = 'The pick-up date cannot be in the past.';
        } elseif (strlen($notes) > 255) {
            $error = 'Notes must be shorter than 255 characters.';
        } elseif ((int)$book['quantity'] <= 0) {
            $error = 'No copies of that book are on the shelf right now.';
        } elseif (has_open_request($conn, $studentId, $bookId)) {
            $error = 'You already have an open request for that book.';
        } else {
            if (add_request($conn, $studentId, $bookId, $pickupDate, $notes)) {
                log_activity($conn, 'Requested "' . $book['title'] . '"');
                set_flash('success', 'Request sent. A librarian will review it.');
                redirect('index.php?page=student');
            }
            $error = 'Could not send the request.';
        }
    }

    /* ---------------- UPDATE (only while pending) ---------------- */
    if ($action === 'update' && is_post()) {
        csrf_check();

        $id         = (int)($_GET['id'] ?? 0);
        $bookId     = (int)($_POST['book_id'] ?? 0);
        $pickupDate = trim($_POST['pickup_date'] ?? '');
        $notes      = trim($_POST['notes'] ?? '');
        $original   = get_request($conn, $id, $studentId);

        $editing = ['id' => $id, 'book_id' => $bookId,
                    'pickup_date' => $pickupDate, 'notes' => $notes];

        // ===== NULL / EMPTY VALIDATION ON UPDATE =====
        if (!$original) {
            set_flash('error', 'That request does not belong to you.');
            redirect('index.php?page=student');
        } elseif ($original['status'] !== 'pending') {
            set_flash('error', 'Only pending requests can be edited.');
            redirect('index.php?page=student');
        } elseif ($bookId <= 0 || is_blank($pickupDate)) {
            $error = 'No field can be left empty (NULL). Book and pick-up date are required.';
        } elseif (!valid_date($pickupDate)) {
            $error = 'Choose a valid pick-up date.';
        } elseif (strlen($notes) > 255) {
            $error = 'Notes must be shorter than 255 characters.';
        } elseif (has_open_request($conn, $studentId, $bookId, $id)) {
            $error = 'You already have another open request for that book.';
        } else {
            if (update_request($conn, $id, $studentId, $bookId, $pickupDate, $notes)) {
                log_activity($conn, 'Updated borrow request #' . $id);
                set_flash('success', 'Request updated.');
                redirect('index.php?page=student');
            }
            $error = 'Update failed.';
        }
    }

    /* ---------------- READ one row into the form ---------------- */
    if ($action === 'edit' && !$editing) {
        $editing = get_request($conn, (int)($_GET['id'] ?? 0), $studentId);
        if (!$editing) {
            set_flash('error', 'That request does not belong to you.');
            redirect('index.php?page=student');
        }
    }

    /* ---------------- DELETE (cancel) ---------------- */
    if ($action === 'delete') {
        csrf_check();
        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0 && delete_request($conn, $id, $studentId)) {
            log_activity($conn, 'Cancelled borrow request #' . $id);
            set_flash('success', 'Request cancelled.');
        } else {
            set_flash('error', 'Only your own pending requests can be cancelled.');
        }
        redirect('index.php?page=student');
    }

    /* ---------------- Data for the view ---------------- */
    $requests  = get_student_requests($conn, $studentId);
    $books     = get_books($conn);
    $available = get_available_books($conn);
    $stats     = get_student_stats($conn, $studentId);

    // FEATURE 2: fine that is building up right now on late books.
    $liveFine = 0.0;
    foreach ($requests as $r) {
        if ($r['status'] === 'issued') {
            $liveFine += calculate_fine($r['due_date']);
        }
    }

    require __DIR__ . '/../views/student/dashboard.php';
}
