<?php
// ================================================================
// CONTROLLER: login / register / logout
// ================================================================

/* ===================== LOGIN (all 4 roles) ===================== */
function login_controller($conn) {
    // Already signed in? Go straight to your own dashboard.
    if (is_logged_in()) {
        redirect('index.php?page=' . current_role());
    }

    $error = '';
    // COOKIE: "remember me" only refills the username, never the password.
    $prefill = $_COOKIE['remember_user'] ?? '';

    if (is_post()) {
        csrf_check();

        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        // ---- PHP validation ----
        if (is_blank($username) || is_blank($password)) {
            $error = 'Enter both your username and password.';
        } else {
            $user = find_user_by_username($conn, $username);

            // The same message for a wrong username and a wrong password, so
            // nobody can find out which usernames exist.
            if (!$user || !password_verify($password, $user['password'])) {
                $error = 'Wrong username or password.';
            } elseif ($user['status'] === 'suspended') {
                $error = 'This account is suspended. Please contact the administrator.';
            } else {
                // SECURITY: a fresh session id blocks session-fixation attacks.
                session_regenerate_id(true);

                $_SESSION['user'] = [
                    'id'         => (int)$user['id'],
                    'name'       => $user['name'],
                    'username'   => $user['username'],
                    'email'      => $user['email'],
                    'contact'    => $user['contact'],
                    'role'       => $user['role'],
                    'created_at' => $user['created_at'],
                ];
                $_SESSION['last_active'] = time();

                if ($remember) {
                    setcookie('remember_user', $username, [
                        'expires'  => time() + 60 * 60 * 24 * 30,
                        'path'     => '/',
                        'httponly' => true,
                        'samesite' => 'Lax',
                    ]);
                } else {
                    setcookie('remember_user', '', time() - 3600, '/');
                }

                log_activity($conn, 'Signed in');
                redirect('index.php?page=' . $user['role']);
            }
        }
    }

    require __DIR__ . '/../views/auth/login.php';
}

/* ============ REGISTER (student / librarian / visitor) ============ */
function register_controller($conn) {
    if (is_logged_in()) {
        redirect('index.php?page=' . current_role());
    }

    $error = '';
    $success = '';
    $old = ['name' => '', 'email' => '', 'contact' => '', 'username' => '', 'role' => 'student'];

    if (is_post()) {
        csrf_check();

        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $contact  = trim($_POST['contact'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';
        $role     = $_POST['role'] ?? '';

        $old = compact('name', 'email', 'contact', 'username', 'role');

        // Nobody can sign up as an admin: only these three are accepted.
        $allowedRoles = ['student', 'librarian', 'visitor'];

        // ---- PHP validation (the real gate) ----
        if (is_blank($name) || is_blank($email) || is_blank($contact)
            || is_blank($username) || is_blank($password)) {
            $error = 'Fill in every field.';
        } elseif (!in_array($role, $allowedRoles, true)) {
            $error = 'Choose an account type.';
        } elseif (strlen($name) < 3) {
            $error = 'Name must be at least 3 characters.';
        } elseif (!valid_email($email)) {
            $error = 'Enter a valid email address.';
        } elseif (!valid_contact($contact)) {
            $error = 'Enter a valid contact number (6-20 digits).';
        } elseif (!preg_match('/^[A-Za-z0-9_]{4,20}$/', $username)) {
            $error = 'Username must be 4-20 letters, numbers or underscores.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif ($password !== $confirm) {
            $error = 'The two passwords do not match.';
        } elseif (username_exists($conn, $username)) {
            $error = 'That username is already taken.';
        } else {
            if (create_user($conn, $name, $email, $contact, $username, $password, $role)) {
                log_activity($conn, 'New ' . $role . ' account registered: ' . $username);
                set_flash('success', 'Account created. You can sign in now.');
                redirect('index.php?page=login');
            }
            $error = 'Could not create the account. Please try again.';
        }
    }

    require __DIR__ . '/../views/auth/register.php';
}

/* ===================== LOGOUT ===================== */
function logout_controller($conn) {
    if (is_logged_in()) {
        log_activity($conn, 'Signed out');
    }
    $_SESSION = [];
    session_regenerate_id(true);
    session_destroy();
    setcookie('remember_user', '', time() - 3600, '/');
    session_start();
    set_flash('success', 'You have been signed out.');
    redirect('index.php?page=login');
}
