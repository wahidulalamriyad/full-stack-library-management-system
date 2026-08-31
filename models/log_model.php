<?php
// ================================================================
// MODEL: activity_log
// Every important action is written here so the admin can audit it.
// ================================================================

function log_activity($conn, $action) {
    $user = current_user();
    $id       = $user['id']       ?? null;
    $username = $user['username'] ?? 'guest';
    $role     = $user['role']     ?? 'guest';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    $sql  = "INSERT INTO activity_log (user_id, username, role, action, ip)
             VALUES (?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'issss', $id, $username, $role, $action, $ip);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}

function get_logs($conn, $limit = 20) {
    $sql  = "SELECT * FROM activity_log ORDER BY id DESC LIMIT ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $limit);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function search_logs($conn, $term, $limit = 50) {
    $like = '%' . $term . '%';
    $sql  = "SELECT * FROM activity_log
             WHERE username LIKE ? OR role LIKE ? OR action LIKE ? OR ip LIKE ?
             ORDER BY id DESC LIMIT ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ssssi', $like, $like, $like, $like, $limit);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}
