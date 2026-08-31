<?php
// ================================================================
// MODEL: books  (created and maintained by librarians)
// ================================================================

function get_books($conn) {
    $sql = "SELECT id, title, author, category, isbn, quantity, price
            FROM books ORDER BY id DESC";
    $res = mysqli_query($conn, $sql);
    return mysqli_fetch_all($res, MYSQLI_ASSOC);
}

function get_book($conn, $id) {
    $sql  = "SELECT id, title, author, category, isbn, quantity, price
             FROM books WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $id);
    mysqli_stmt_execute($stmt);
    $row = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt));
    mysqli_stmt_close($stmt);
    return $row;
}

function search_books($conn, $term) {
    $like = '%' . $term . '%';
    $sql  = "SELECT id, title, author, category, isbn, quantity, price
             FROM books
             WHERE title LIKE ? OR author LIKE ? OR category LIKE ? OR isbn LIKE ?
             ORDER BY id DESC";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ssss', $like, $like, $like, $like);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

function add_book($conn, $title, $author, $category, $isbn, $quantity, $price, $addedBy) {
    $sql  = "INSERT INTO books (title, author, category, isbn, quantity, price, added_by)
             VALUES (?, ?, ?, ?, ?, ?, ?)";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ssssidi',
        $title, $author, $category, $isbn, $quantity, $price, $addedBy);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function update_book($conn, $id, $title, $author, $category, $isbn, $quantity, $price) {
    $sql  = "UPDATE books
             SET title = ?, author = ?, category = ?, isbn = ?, quantity = ?, price = ?
             WHERE id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'ssssidi',
        $title, $author, $category, $isbn, $quantity, $price, $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

function delete_book($conn, $id) {
    $stmt = mysqli_prepare($conn, "DELETE FROM books WHERE id = ?");
    mysqli_stmt_bind_param($stmt, 'i', $id);
    $ok = mysqli_stmt_execute($stmt);
    mysqli_stmt_close($stmt);
    return $ok;
}

// Used when a book is issued (-1 copy) or returned (+1 copy).
function change_book_stock($conn, $id, $amount) {
    $stmt = mysqli_prepare($conn,
        "UPDATE books SET quantity = quantity + ? WHERE id = ? AND quantity + ? >= 0");
    mysqli_stmt_bind_param($stmt, 'iii', $amount, $id, $amount);
    $ok = mysqli_stmt_execute($stmt);
    $changed = mysqli_stmt_affected_rows($stmt) > 0;
    mysqli_stmt_close($stmt);
    return $ok && $changed;
}

// LIBRARIAN FEATURE: books that are running out.
function get_low_stock_books($conn) {
    $limit = LOW_STOCK;
    $sql   = "SELECT id, title, author, category, isbn, quantity, price
              FROM books WHERE quantity <= ? ORDER BY quantity ASC";
    $stmt  = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, 'i', $limit);
    mysqli_stmt_execute($stmt);
    $rows = mysqli_fetch_all(mysqli_stmt_get_result($stmt), MYSQLI_ASSOC);
    mysqli_stmt_close($stmt);
    return $rows;
}

// Books a student is allowed to request (at least one copy on the shelf).
function get_available_books($conn) {
    $sql = "SELECT id, title, author, category, isbn, quantity, price
            FROM books WHERE quantity > 0 ORDER BY title ASC";
    $res = mysqli_query($conn, $sql);
    return mysqli_fetch_all($res, MYSQLI_ASSOC);
}
