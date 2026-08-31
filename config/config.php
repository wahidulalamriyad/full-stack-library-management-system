<?php
// ================================================================
// CONFIG - database connection, session setup and app settings
// Everything in the project starts from here.
// ================================================================

/* ---------- 1. Database settings (change if your XAMPP differs) ---------- */
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'library_db');

/* ---------- 2. App settings ---------- */
define('APP_NAME',     'LibSys');
define('CURRENCY',     '$');
define('LOAN_DAYS',    14);   // how many days a student may keep a book
define('FINE_PER_DAY', 5);    // fine charged for every day a book is late
define('LOW_STOCK',    3);    // a book at or below this count is "low stock"
define('SESSION_TIMEOUT', 1800); // auto logout after 30 minutes of no activity

/* ---------- 3. Start a hardened session ---------- */
// SECURITY: the cookie cannot be read by JavaScript (httponly) and is not
// sent on cross-site requests (samesite), which blocks most XSS/CSRF tricks.
if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

/* ---------- 4. Connect to MySQL (procedural mysqli) ---------- */
$conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
if (!$conn) {
    die('Database connection failed. Did you import database.sql? Details: '
        . mysqli_connect_error());
}
mysqli_set_charset($conn, 'utf8mb4');

/* ---------- 5. Create the default admin the first time the app runs ---------- */
// Runs only when there is no admin yet.  Login: admin / admin123
$check = mysqli_query($conn, "SELECT id FROM users WHERE role = 'admin' LIMIT 1");
if ($check && mysqli_num_rows($check) === 0) {
    $hash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = mysqli_prepare($conn,
        "INSERT INTO users (name, email, contact, username, password, role)
         VALUES ('Administrator', 'admin@libsys.test', '0000000000', 'admin', ?, 'admin')");
    mysqli_stmt_bind_param($stmt, 's', $hash);
    mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
}
