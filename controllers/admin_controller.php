<?php
// ================================================================
// CONTROLLER: ADMIN dashboard
// CRUD  : user accounts (every role)
// Extras: 1) account status + membership approvals
//         2) system activity log
//         3) live statistics panel (AJAX)
// ================================================================

function admin_controller($conn) {
    $action     = $_GET['action'] ?? 'list';
    $roleFilter = $_GET['role']   ?? '';
    $me         = current_user();

    $error   = '';
    $editing = null;   // when filled the form switches to "edit" mode

    // Only these role names are ever accepted from the browser.
    $roles    = ['admin', 'librarian', 'student', 'visitor'];
    $statuses = ['active', 'suspended'];

    /* ---------------- CREATE ---------------- */
    if ($action === 'add' && is_post()) {
        csrf_check();

        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $contact  = trim($_POST['contact'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $role     = $_POST['role'] ?? '';

        if (is_blank($name) || is_blank($email) || is_blank($contact)
            || is_blank($username) || is_blank($password)) {
            $error = 'Fill in every field.';
        } elseif (!in_array($role, $roles, true)) {
            $error = 'Choose a valid role.';
        } elseif (!valid_email($email)) {
            $error = 'Enter a valid email address.';
        } elseif (!valid_contact($contact)) {
            $error = 'Enter a valid contact number.';
        } elseif (!preg_match('/^[A-Za-z0-9_]{4,20}$/', $username)) {
            $error = 'Username must be 4-20 letters, numbers or underscores.';
        } elseif (strlen($password) < 6) {
            $error = 'Password must be at least 6 characters.';
        } elseif (username_exists($conn, $username)) {
            $error = 'That username is already taken.';
        } else {
            if (create_user($conn, $name, $email, $contact, $username, $password, $role)) {
                log_activity($conn, 'Created ' . $role . ' account: ' . $username);
                set_flash('success', 'Account created.');
                redirect('index.php?page=admin');
            }
            $error = 'Could not create the account.';
        }
    }

    /* ---------------- UPDATE ---------------- */
    if ($action === 'update' && is_post()) {
        csrf_check();

        $id       = (int)($_GET['id'] ?? 0);
        $name     = trim($_POST['name'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $contact  = trim($_POST['contact'] ?? '');
        $username = trim($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';          // blank = keep old password
        $role     = $_POST['role'] ?? '';
        $status   = $_POST['status'] ?? 'active';

        // Keep the typed values on screen when validation fails.
        $editing = ['id' => $id, 'name' => $name, 'email' => $email, 'contact' => $contact,
                    'username' => $username, 'role' => $role, 'status' => $status];

        // ===== NULL / EMPTY VALIDATION ON UPDATE =====
        if (is_blank($name) || is_blank($email) || is_blank($contact) || is_blank($username)) {
            $error = 'No field can be left empty (NULL). All fields are required.';
        } elseif (!in_array($role, $roles, true) || !in_array($status, $statuses, true)) {
            $error = 'Choose a valid role and status.';
        } elseif (!valid_email($email)) {
            $error = 'Enter a valid email address.';
        } elseif (!valid_contact($contact)) {
            $error = 'Enter a valid contact number.';
        } elseif (username_exists($conn, $username, $id)) {
            $error = 'Another account already uses that username.';
        } elseif ($password !== '' && strlen($password) < 6) {
            $error = 'Password must be at least 6 characters (or leave it blank).';
        } elseif ($id === (int)$me['id'] && ($role !== 'admin' || $status !== 'active')) {
            $error = 'You cannot remove your own admin access.';
        } else {
            if (update_user($conn, $id, $name, $email, $contact, $username, $role, $status)) {
                if ($password !== '') {
                    update_password($conn, $id, $password);
                }
                log_activity($conn, 'Updated account #' . $id . ' (' . $username . ')');
                set_flash('success', 'Account updated.');
                redirect('index.php?page=admin');
            }
            $error = 'Update failed.';
        }
    }

    /* ---------------- READ one row into the form ---------------- */
    if ($action === 'edit' && !$editing) {
        $editing = get_user($conn, (int)($_GET['id'] ?? 0));
        if (!$editing) {
            set_flash('error', 'That account no longer exists.');
            redirect('index.php?page=admin');
        }
    }

    /* ---------------- DELETE ---------------- */
    if ($action === 'delete') {
        csrf_check();
        $id = (int)($_GET['id'] ?? 0);

        if ($id === (int)$me['id']) {
            set_flash('error', 'You cannot delete your own account.');
        } elseif ($id > 0 && delete_user($conn, $id)) {
            log_activity($conn, 'Deleted account #' . $id);
            set_flash('success', 'Account deleted.');
        } else {
            set_flash('error', 'Could not delete that account.');
        }
        redirect('index.php?page=admin');
    }

    /* -------- FEATURE 1a: suspend / activate an account -------- */
    if ($action === 'status') {
        csrf_check();
        $id     = (int)($_GET['id'] ?? 0);
        $status = $_GET['to'] ?? '';

        if ($id === (int)$me['id']) {
            set_flash('error', 'You cannot suspend your own account.');
        } elseif (in_array($status, $statuses, true) && set_user_status($conn, $id, $status)) {
            log_activity($conn, 'Set account #' . $id . ' to ' . $status);
            set_flash('success', 'Account is now ' . $status . '.');
        } else {
            set_flash('error', 'Could not change that account.');
        }
        redirect('index.php?page=admin');
    }

    /* -------- FEATURE 1b: approve / reject membership upgrades -------- */
    if ($action === 'membership') {
        csrf_check();
        $id       = (int)($_GET['id'] ?? 0);
        $decision = $_GET['to'] ?? '';
        $app      = get_application_by_id($conn, $id);

        if ($app && in_array($decision, ['approved', 'rejected'], true)
            && decide_application($conn, $id, $decision)) {

            if ($decision === 'approved') {
                // Approving turns the visitor into a student.
                set_user_role($conn, (int)$app['visitor_id'], 'student');
            }
            log_activity($conn, 'Membership application #' . $id . ' ' . $decision);
            set_flash('success', 'Application ' . $decision . '.');
        } else {
            set_flash('error', 'Could not update that application.');
        }
        redirect('index.php?page=admin');
    }

    /* ---------------- Data for the view ---------------- */
    if (!in_array($roleFilter, $roles, true)) {
        $roleFilter = '';
    }
    $users        = get_users($conn, $roleFilter);
    $stats        = get_system_stats($conn);
    $logs         = get_logs($conn, 12);
    $applications = get_pending_applications($conn);

    require __DIR__ . '/../views/admin/dashboard.php';
}
