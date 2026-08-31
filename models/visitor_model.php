<?php
// ================================================================
// MODEL: visit_passes + membership_applications + book_suggestions
// Everything a visitor owns.
// ================================================================

/* ---------------- Visit passes (the visitor's CRUD table) ---------------- */

// Builds a short human-readable pass code, e.g. LIB-4821-K7.
function make_pass_code() {
    $letters = strtoupper(substr(str_shuffle('ABCDEFGHJKLMNPQRSTUVWXYZ'), 0, 2));
    return 'LIB-' . random_int(1000, 9999) . '-' . $letters;
}

function add_pass($conn, $visitorId, $visitDate, $purpose, $guests) {
    $code = make_pass_code();
    $sql  = "INSERT INTO visit_passes (visitor_id, pass_code, visit_date, purpose, guests)
             VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'isssi', $visitorId, $code, $visitDate, $purpose, $guests);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function get_passes($conn, $visitorId) {
    $sql  = "SELECT * FROM visit_passes WHERE visitor_id = ? ORDER BY visit_date DESC, id DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $visitorId);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function search_passes($conn, $visitorId, $term) {
    $like = '%' . $term . '%';
    $sql  = "SELECT * FROM visit_passes
             WHERE visitor_id = ?
               AND (purpose LIKE ? OR pass_code LIKE ? OR visit_date LIKE ? OR status LIKE ?)
             ORDER BY visit_date DESC, id DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'issss', $visitorId, $like, $like, $like, $like);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function get_pass($conn, $id, $visitorId) {
    $sql  = "SELECT * FROM visit_passes WHERE id = ? AND visitor_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ii', $id, $visitorId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function update_pass($conn, $id, $visitorId, $visitDate, $purpose, $guests, $status) {
    $sql  = "UPDATE visit_passes
             SET visit_date = ?, purpose = ?, guests = ?, status = ?
             WHERE id = ? AND visitor_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ssisii', $visitDate, $purpose, $guests, $status, $id, $visitorId);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function delete_pass($conn, $id, $visitorId) {
    $stmt = mysqli_prepare($conn, "DELETE FROM visit_passes WHERE id = ? AND visitor_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $id, $visitorId);
    mysqli_stmt_execute($stmt);
    $deleted = mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $deleted;
}

// Only one booking per calendar day per visitor.
function pass_exists_on_date($conn, $visitorId, $visitDate, $excludeId = 0) {
    $sql  = "SELECT id FROM visit_passes
             WHERE visitor_id = ? AND visit_date = ? AND status = 'booked' AND id != ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'isi', $visitorId, $visitDate, $excludeId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $exists = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $exists;
}

/* ---------------- Membership application (visitor feature) ---------------- */

function get_application($conn, $visitorId) {
    $sql  = "SELECT * FROM membership_applications
             WHERE visitor_id = ? ORDER BY id DESC LIMIT 1";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $visitorId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function add_application($conn, $visitorId, $reason) {
    $sql  = "INSERT INTO membership_applications (visitor_id, reason) VALUES (?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'is', $visitorId, $reason);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

// Admin reads this list on the dashboard.
function get_pending_applications($conn) {
    $sql = "SELECT a.*, u.name, u.username, u.email
            FROM membership_applications a
            JOIN users u ON u.id = a.visitor_id
            WHERE a.status = 'pending'
            ORDER BY a.id DESC";
    $res = mysqli_query($conn, $sql);
    return mysqli_fetch_all($res, MYSQLI_ASSOC);
}

function decide_application($conn, $id, $decision) {
    $sql  = "UPDATE membership_applications SET status = ? WHERE id = ? AND status = 'pending'";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'si', $decision, $id);
    mysqli_stmt_execute($stmt);
    $done = mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $done;
}

function get_application_by_id($conn, $id) {
    $stmt = mysqli_prepare($conn, "SELECT * FROM membership_applications WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

/* ---------------- Book suggestion box (visitor feature) ---------------- */

function add_suggestion($conn, $visitorId, $title, $author, $reason) {
    $sql  = "INSERT INTO book_suggestions (visitor_id, title, author, reason)
             VALUES (?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'isss', $visitorId, $title, $author, $reason);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function get_suggestions($conn, $visitorId) {
    $sql  = "SELECT * FROM book_suggestions WHERE visitor_id = ? ORDER BY id DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $visitorId);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function delete_suggestion($conn, $id, $visitorId) {
    $stmt = mysqli_prepare($conn, "DELETE FROM book_suggestions WHERE id = ? AND visitor_id = ?");
    mysqli_stmt_bind_param($stmt, 'ii', $id, $visitorId);
    mysqli_stmt_execute($stmt);
    $deleted = mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $deleted;
}

function get_visitor_stats($conn, $visitorId) {
    $sql  = "SELECT
               (SELECT COUNT(*) FROM visit_passes WHERE visitor_id = ? AND status = 'booked') AS booked,
               (SELECT COUNT(*) FROM visit_passes WHERE visitor_id = ? AND status = 'booked' AND visit_date >= CURDATE()) AS upcoming,
               (SELECT COUNT(*) FROM book_suggestions WHERE visitor_id = ?) AS suggestions";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'iii', $visitorId, $visitorId, $visitorId);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return [
        'booked'      => (int)($row['booked']      ?? 0),
        'upcoming'    => (int)($row['upcoming']    ?? 0),
        'suggestions' => (int)($row['suggestions'] ?? 0),
    ];
}
