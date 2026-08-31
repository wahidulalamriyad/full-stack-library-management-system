<?php
// ================================================================
// MODEL: users  (admin, librarian, student, visitor all live here)
// Every query uses a prepared statement -> no SQL injection.
// ================================================================

function find_user_by_username($conn, $username) {
    $sql  = "SELECT * FROM users WHERE username = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 's', $username);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function get_user($conn, $id) {
    $sql  = "SELECT id, name, email, contact, username, role, status, created_at
             FROM users WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

// $role = '' means "every role".
function get_users($conn, $role = '') {
    if ($role === '') {
        $sql  = "SELECT id, name, email, contact, username, role, status, created_at
                 FROM users ORDER BY id DESC";
        $stmt = mysqli_prepare($conn, $sql);
    } else {
        $sql  = "SELECT id, name, email, contact, username, role, status, created_at
                 FROM users WHERE role = ? ORDER BY id DESC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 's', $role);
    }
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function search_users($conn, $term, $role = '') {
    $like = '%' . $term . '%';
    if ($role === '') {
        $sql = "SELECT id, name, email, contact, username, role, status, created_at
                FROM users
                WHERE name LIKE ? OR username LIKE ? OR email LIKE ? OR contact LIKE ?
                ORDER BY id DESC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'ssss', $like, $like, $like, $like);
    } else {
        $sql = "SELECT id, name, email, contact, username, role, status, created_at
                FROM users
                WHERE role = ?
                  AND (name LIKE ? OR username LIKE ? OR email LIKE ? OR contact LIKE ?)
                ORDER BY id DESC";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, 'sssss', $role, $like, $like, $like, $like);
    }
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function username_exists($conn, $username, $excludeId = 0) {
    $sql  = "SELECT id FROM users WHERE username = ? AND id != ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'si', $username, $excludeId);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_store_result($stmt);
    $exists = mysqli_stmt_num_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $exists;
}

function create_user($conn, $name, $email, $contact, $username, $password, $role) {
    // SECURITY: passwords are hashed, never stored as plain text.
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $sql  = "INSERT INTO users (name, email, contact, username, password, role)
             VALUES (?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ssssss', $name, $email, $contact, $username, $hash, $role);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function update_user($conn, $id, $name, $email, $contact, $username, $role, $status) {
    $sql  = "UPDATE users
             SET name = ?, email = ?, contact = ?, username = ?, role = ?, status = ?
             WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ssssssi', $name, $email, $contact, $username, $role, $status, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function update_password($conn, $id, $password) {
    $hash = password_hash($password, PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $hash, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function delete_user($conn, $id) {
    $stmt = mysqli_prepare($conn, "DELETE FROM users WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

// ADMIN FEATURE: suspend or re-activate an account without deleting it.
function set_user_status($conn, $id, $status) {
    $stmt = mysqli_prepare($conn, "UPDATE users SET status = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $status, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function set_user_role($conn, $id, $role) {
    $stmt = mysqli_prepare($conn, "UPDATE users SET role = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'si', $role, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

// ADMIN FEATURE: numbers for the live statistics panel.
function get_system_stats($conn) {
    $stats = [
        'admins' => 0, 'librarians' => 0, 'students' => 0, 'visitors' => 0,
        'suspended' => 0, 'books' => 0, 'copies' => 0,
        'pending_requests' => 0, 'issued' => 0, 'passes' => 0,
    ];

    $res = mysqli_query($conn, "SELECT role, COUNT(*) AS total FROM users GROUP BY role");
    while ($row = mysqli_fetch_assoc($res)) {
        if ($row['role'] === 'admin')     $stats['admins']     = (int)$row['total'];
        if ($row['role'] === 'librarian') $stats['librarians'] = (int)$row['total'];
        if ($row['role'] === 'student')   $stats['students']   = (int)$row['total'];
        if ($row['role'] === 'visitor')   $stats['visitors']   = (int)$row['total'];
    }

    $one = function ($conn, $sql) {
        $res = mysqli_query($conn, $sql);
        $row = mysqli_fetch_row($res);
        return (int)($row[0] ?? 0);
    };

    $stats['suspended']        = $one($conn, "SELECT COUNT(*) FROM users WHERE status = 'suspended'");
    $stats['books']            = $one($conn, "SELECT COUNT(*) FROM books");
    $stats['copies']           = $one($conn, "SELECT COALESCE(SUM(quantity),0) FROM books");
    $stats['pending_requests'] = $one($conn, "SELECT COUNT(*) FROM borrow_requests WHERE status = 'pending'");
    $stats['issued']           = $one($conn, "SELECT COUNT(*) FROM borrow_requests WHERE status = 'issued'");
    $stats['passes']           = $one($conn, "SELECT COUNT(*) FROM visit_passes WHERE status = 'booked'");

    return $stats;
}
