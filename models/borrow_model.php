<?php
// ================================================================
// MODEL: borrow_requests
// Students create/edit/cancel them; librarians issue and return them.
// ================================================================

/* ---------------- Student side ---------------- */

function add_request($conn, $studentId, $bookId, $pickupDate, $notes) {
    $today = date('Y-m-d');
    $sql   = "INSERT INTO borrow_requests (student_id, book_id, pickup_date, notes, request_date)
              VALUES (?, ?, ?, ?, ?)";
    $stmt  = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'iisss', $studentId, $bookId, $pickupDate, $notes, $today);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function get_student_requests($conn, $studentId) {
    $sql  = "SELECT r.*, b.title, b.author
             FROM borrow_requests r
             JOIN books b ON b.id = r.book_id
             WHERE r.student_id = ?
             ORDER BY r.id DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $studentId);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function search_student_requests($conn, $studentId, $term) {
    $like = '%' . $term . '%';
    $sql  = "SELECT r.*, b.title, b.author
             FROM borrow_requests r
             JOIN books b ON b.id = r.book_id
             WHERE r.student_id = ?
               AND (b.title LIKE ? OR b.author LIKE ? OR r.status LIKE ? OR r.notes LIKE ?)
             ORDER BY r.id DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'issss', $studentId, $like, $like, $like, $like);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

// The $studentId argument makes sure one student cannot open another
// student's request by typing a different id in the address bar.
function get_request($conn, $id, $studentId = 0) {
    if ($studentId > 0) {
        $sql  = "SELECT r.*, b.title, b.author
                 FROM borrow_requests r
                 JOIN books b ON b.id = r.book_id
                 WHERE r.id = ? AND r.student_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ii', $id, $studentId);
    } else {
        $sql  = "SELECT r.*, b.title, b.author
                 FROM borrow_requests r
                 JOIN books b ON b.id = r.book_id
                 WHERE r.id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'i', $id);
    }
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

// A student may only edit a request that is still pending.
function update_request($conn, $id, $studentId, $bookId, $pickupDate, $notes) {
    $sql  = "UPDATE borrow_requests
             SET book_id = ?, pickup_date = ?, notes = ?
             WHERE id = ? AND student_id = ? AND status = 'pending'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'issii', $bookId, $pickupDate, $notes, $id, $studentId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function delete_request($conn, $id, $studentId) {
    $sql  = "DELETE FROM borrow_requests
             WHERE id = ? AND student_id = ? AND status = 'pending'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $id, $studentId);
    mysqli_stmt_execute($stmt);
    $deleted = mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $deleted;
}

// Stops a student from requesting the same book twice at the same time.
function has_open_request($conn, $studentId, $bookId, $excludeId = 0) {
    $sql  = "SELECT id FROM borrow_requests
             WHERE student_id = ? AND book_id = ?
               AND status IN ('pending','issued') AND id != ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'iii', $studentId, $bookId, $excludeId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $exists = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $exists;
}

// STUDENT FEATURE: numbers for the due-date and fine tracker.
function get_student_stats($conn, $studentId) {
    $sql = "SELECT
              SUM(status = 'pending')  AS pending,
              SUM(status = 'issued')   AS issued,
              SUM(status = 'returned') AS returned,
              SUM(status = 'issued' AND due_date < CURDATE()) AS overdue,
              COALESCE(SUM(fine), 0)   AS paid_fine
            FROM borrow_requests WHERE student_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $studentId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return [
        'pending'   => (int)($row['pending']   ?? 0),
        'issued'    => (int)($row['issued']    ?? 0),
        'returned'  => (int)($row['returned']  ?? 0),
        'overdue'   => (int)($row['overdue']   ?? 0),
        'paid_fine' => (float)($row['paid_fine'] ?? 0),
    ];
}

/* ---------------- Librarian side ---------------- */

function get_all_requests($conn) {
    $sql = "SELECT r.*, b.title, b.author, u.name AS student_name, u.username AS student_username
            FROM borrow_requests r
            JOIN books b ON b.id = r.book_id
            JOIN users u ON u.id = r.student_id
            ORDER BY FIELD(r.status,'pending','issued','returned','rejected'), r.id DESC";
    $res = mysqli_query($conn, $sql);
    return mysqli_fetch_all($res, MYSQLI_ASSOC);
}

function search_all_requests($conn, $term) {
    $like = '%' . $term . '%';
    $sql  = "SELECT r.*, b.title, b.author, u.name AS student_name, u.username AS student_username
             FROM borrow_requests r
             JOIN books b ON b.id = r.book_id
             JOIN users u ON u.id = r.student_id
             WHERE b.title LIKE ? OR u.name LIKE ? OR u.username LIKE ? OR r.status LIKE ?
             ORDER BY FIELD(r.status,'pending','issued','returned','rejected'), r.id DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ssss', $like, $like, $like, $like);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

// LIBRARIAN FEATURE (issue desk): hand the book over and start the clock.
function issue_request($conn, $id) {
    $issue = date('Y-m-d');
    $due   = date('Y-m-d', strtotime('+' . LOAN_DAYS . ' days'));
    $sql   = "UPDATE borrow_requests
              SET status = 'issued', issue_date = ?, due_date = ?
              WHERE id = ? AND status = 'pending'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ssi', $issue, $due, $id);
    mysqli_stmt_execute($stmt);
    $done = mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $done;
}

// LIBRARIAN FEATURE (issue desk): take the book back and store the fine.
function return_request($conn, $id, $fine) {
    $today = date('Y-m-d');
    $sql   = "UPDATE borrow_requests
              SET status = 'returned', return_date = ?, fine = ?
              WHERE id = ? AND status = 'issued'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'sdi', $today, $fine, $id);
    mysqli_stmt_execute($stmt);
    $done = mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $done;
}

function reject_request($conn, $id) {
    $sql  = "UPDATE borrow_requests SET status = 'rejected'
             WHERE id = ? AND status = 'pending'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $done = mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $done;
}

function get_librarian_stats($conn) {
    $one = function ($conn, $sql) {
        $res = mysqli_query($conn, $sql);
        $row = mysqli_fetch_row($res);
        return (int)($row[0] ?? 0);
    };
    return [
        'books'     => $one($conn, "SELECT COUNT(*) FROM books"),
        'copies'    => $one($conn, "SELECT COALESCE(SUM(quantity),0) FROM books"),
        'low_stock' => $one($conn, "SELECT COUNT(*) FROM books WHERE quantity <= " . (int)LOW_STOCK),
        'pending'   => $one($conn, "SELECT COUNT(*) FROM borrow_requests WHERE status = 'pending'"),
        'issued'    => $one($conn, "SELECT COUNT(*) FROM borrow_requests WHERE status = 'issued'"),
        'overdue'   => $one($conn, "SELECT COUNT(*) FROM borrow_requests WHERE status = 'issued' AND due_date < CURDATE()"),
    ];
}
