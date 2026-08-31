<?php
// ================================================================
// CONTROLLER: LIBRARIAN dashboard
// CRUD  : books
// Extras: 1) issue & return desk (stock updates automatically)
//         2) low stock alert list
//         3) export the whole catalogue as a CSV file
// ================================================================

function librarian_controller($conn) {
    $action = $_GET['action'] ?? 'list';
    $me     = current_user();

    $error   = '';
    $editing = null;

    /* ---------------- CREATE ---------------- */
    if ($action === 'add' && is_post()) {
        csrf_check();

        $title    = trim($_POST['title'] ?? '');
        $author   = trim($_POST['author'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $isbn     = trim($_POST['isbn'] ?? '');
        $quantity = trim($_POST['quantity'] ?? '');
        $price    = trim($_POST['price'] ?? '');

        if (is_blank($title) || is_blank($author) || is_blank($category)
            || is_blank($isbn) || is_blank($quantity) || is_blank($price)) {
            $error = 'Fill in every field.';
        } elseif (!ctype_digit($quantity)) {
            $error = 'Quantity must be a whole number (0 or more).';
        } elseif (!is_numeric($price) || (float)$price < 0) {
            $error = 'Price must be a number (0 or more).';
        } elseif (!preg_match('/^[0-9\-]{6,20}$/', $isbn)) {
            $error = 'ISBN may only contain digits and dashes (6-20 characters).';
        } else {
            if (add_book($conn, $title, $author, $category, $isbn,
                         (int)$quantity, (float)$price, (int)$me['id'])) {
                log_activity($conn, 'Added book: ' . $title);
                set_flash('success', 'Book added to the catalogue.');
                redirect('index.php?page=librarian');
            }
            $error = 'Could not add the book.';
        }
    }

    /* ---------------- UPDATE ---------------- */
    if ($action === 'update' && is_post()) {
        csrf_check();

        $id       = (int)($_GET['id'] ?? 0);
        $title    = trim($_POST['title'] ?? '');
        $author   = trim($_POST['author'] ?? '');
        $category = trim($_POST['category'] ?? '');
        $isbn     = trim($_POST['isbn'] ?? '');
        $quantity = trim($_POST['quantity'] ?? '');
        $price    = trim($_POST['price'] ?? '');

        $editing = ['id' => $id, 'title' => $title, 'author' => $author,
                    'category' => $category, 'isbn' => $isbn,
                    'quantity' => $quantity, 'price' => $price];

        // ===== NULL / EMPTY VALIDATION ON UPDATE =====
        if (is_blank($title) || is_blank($author) || is_blank($category)
            || is_blank($isbn) || is_blank($quantity) || is_blank($price)) {
            $error = 'No field can be left empty (NULL). All fields are required.';
        } elseif (!ctype_digit($quantity)) {
            $error = 'Quantity must be a whole number (0 or more).';
        } elseif (!is_numeric($price) || (float)$price < 0) {
            $error = 'Price must be a number (0 or more).';
        } elseif (!preg_match('/^[0-9\-]{6,20}$/', $isbn)) {
            $error = 'ISBN may only contain digits and dashes (6-20 characters).';
        } else {
            if (update_book($conn, $id, $title, $author, $category, $isbn,
                            (int)$quantity, (float)$price)) {
                log_activity($conn, 'Updated book #' . $id . ': ' . $title);
                set_flash('success', 'Book updated.');
                redirect('index.php?page=librarian');
            }
            $error = 'Update failed.';
        }
    }

    /* ---------------- READ one row into the form ---------------- */
    if ($action === 'edit' && !$editing) {
        $editing = get_book($conn, (int)($_GET['id'] ?? 0));
        if (!$editing) {
            set_flash('error', 'That book no longer exists.');
            redirect('index.php?page=librarian');
        }
    }

    /* ---------------- DELETE ---------------- */
    if ($action === 'delete') {
        csrf_check();
        $id = (int)($_GET['id'] ?? 0);
        if ($id > 0 && delete_book($conn, $id)) {
            log_activity($conn, 'Deleted book #' . $id);
            set_flash('success', 'Book deleted.');
        } else {
            set_flash('error', 'Could not delete that book.');
        }
        redirect('index.php?page=librarian');
    }

    /* -------- FEATURE 1: issue desk -------- */
    if ($action === 'issue') {
        csrf_check();
        $id  = (int)($_GET['id'] ?? 0);
        $req = get_request($conn, $id);

        if (!$req || $req['status'] !== 'pending') {
            set_flash('error', 'That request cannot be issued.');
        } elseif (!change_book_stock($conn, (int)$req['book_id'], -1)) {
            set_flash('error', 'No copies of "' . $req['title'] . '" are on the shelf.');
        } elseif (issue_request($conn, $id)) {
            log_activity($conn, 'Issued "' . $req['title'] . '" to ' . $req['student_id']);
            set_flash('success', 'Book issued. Due in ' . LOAN_DAYS . ' days.');
        } else {
            change_book_stock($conn, (int)$req['book_id'], 1); // undo the stock change
            set_flash('error', 'Could not issue that book.');
        }
        redirect('index.php?page=librarian');
    }

    if ($action === 'return') {
        csrf_check();
        $id  = (int)($_GET['id'] ?? 0);
        $req = get_request($conn, $id);

        if (!$req || $req['status'] !== 'issued') {
            set_flash('error', 'That book is not currently issued.');
        } else {
            $fine = calculate_fine($req['due_date']);       // late days x FINE_PER_DAY
            if (return_request($conn, $id, $fine)) {
                change_book_stock($conn, (int)$req['book_id'], 1);
                log_activity($conn, 'Returned "' . $req['title'] . '", fine ' . money($fine));
                set_flash('success', $fine > 0
                    ? 'Book returned. Fine due: ' . money($fine) . '.'
                    : 'Book returned on time. No fine.');
            } else {
                set_flash('error', 'Could not return that book.');
            }
        }
        redirect('index.php?page=librarian');
    }

    if ($action === 'reject') {
        csrf_check();
        $id = (int)($_GET['id'] ?? 0);
        if (reject_request($conn, $id)) {
            log_activity($conn, 'Rejected borrow request #' . $id);
            set_flash('success', 'Request rejected.');
        } else {
            set_flash('error', 'Could not reject that request.');
        }
        redirect('index.php?page=librarian');
    }

    /* -------- FEATURE 3: CSV export -------- */
    if ($action === 'export') {
        $books = get_books($conn);
        log_activity($conn, 'Exported the book catalogue');

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="book_catalogue_' . date('Y-m-d') . '.csv"');

        $out = fopen('php://output', 'w');
        fputcsv($out, ['ID', 'Title', 'Author', 'Category', 'ISBN', 'Quantity', 'Price']);
        foreach ($books as $b) {
            fputcsv($out, [$b['id'], $b['title'], $b['author'], $b['category'],
                           $b['isbn'], $b['quantity'], $b['price']]);
        }
        fclose($out);
        exit;
    }

    /* ---------------- Data for the view ---------------- */
    $books    = get_books($conn);
    $requests = get_all_requests($conn);
    $lowStock = get_low_stock_books($conn);
    $stats    = get_librarian_stats($conn);

    require __DIR__ . '/../views/librarian/dashboard.php';
}
