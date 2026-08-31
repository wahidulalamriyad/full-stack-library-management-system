-- ================================================================
-- LIBRARY MANAGEMENT SYSTEM - DATABASE
-- Import this file ONCE in phpMyAdmin (Import -> choose file -> Go)
-- ================================================================

CREATE DATABASE IF NOT EXISTS library_db
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE library_db;

-- ----------------------------------------------------------------
-- 1. USERS  (one table holds all 4 roles: admin, librarian, student, visitor)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(100) NOT NULL,
    email      VARCHAR(120) NOT NULL,
    contact    VARCHAR(30)  NOT NULL,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    password   VARCHAR(255) NOT NULL,              -- stored as a password_hash()
    role       ENUM('admin','librarian','student','visitor') NOT NULL DEFAULT 'student',
    status     ENUM('active','suspended') NOT NULL DEFAULT 'active',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- 2. BOOKS  (managed by librarians)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS books (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    title      VARCHAR(200) NOT NULL,
    author     VARCHAR(120) NOT NULL,
    category   VARCHAR(60)  NOT NULL,
    isbn       VARCHAR(30)  NOT NULL,
    quantity   INT NOT NULL DEFAULT 0,             -- copies currently on the shelf
    price      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    added_by   INT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- 3. BORROW REQUESTS  (created by students, handled by librarians)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS borrow_requests (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    student_id   INT NOT NULL,
    book_id      INT NOT NULL,
    pickup_date  DATE NOT NULL,
    notes        VARCHAR(255) NOT NULL DEFAULT '',
    status       ENUM('pending','issued','returned','rejected') NOT NULL DEFAULT 'pending',
    request_date DATE NOT NULL,
    issue_date   DATE NULL,
    due_date     DATE NULL,
    return_date  DATE NULL,
    fine         DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    FOREIGN KEY (student_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (book_id)    REFERENCES books(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- 4. VISIT PASSES  (created by visitors)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS visit_passes (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    visitor_id INT NOT NULL,
    pass_code  VARCHAR(20) NOT NULL,
    visit_date DATE NOT NULL,
    purpose    VARCHAR(150) NOT NULL,
    guests     INT NOT NULL DEFAULT 1,
    status     ENUM('booked','cancelled') NOT NULL DEFAULT 'booked',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (visitor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- 5. MEMBERSHIP APPLICATIONS  (visitor asks admin to upgrade to student)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS membership_applications (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    visitor_id INT NOT NULL,
    reason     VARCHAR(255) NOT NULL,
    status     ENUM('pending','approved','rejected') NOT NULL DEFAULT 'pending',
    applied_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (visitor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- 6. BOOK SUGGESTIONS  (visitor suggestion box)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS book_suggestions (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    visitor_id INT NOT NULL,
    title      VARCHAR(200) NOT NULL,
    author     VARCHAR(120) NOT NULL,
    reason     VARCHAR(255) NOT NULL DEFAULT '',
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (visitor_id) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- 7. ACTIVITY LOG  (audit trail the admin can read)
-- ----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS activity_log (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    user_id    INT NULL,
    username   VARCHAR(50)  NOT NULL,
    role       VARCHAR(20)  NOT NULL,
    action     VARCHAR(255) NOT NULL,
    ip         VARCHAR(45)  NOT NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ----------------------------------------------------------------
-- Sample books so the dashboards are not empty on day one.
-- ----------------------------------------------------------------
INSERT INTO books (title, author, category, isbn, quantity, price) VALUES
('The Pragmatic Programmer', 'Andrew Hunt',      'Programming', '9780201616224', 5, 45.00),
('Clean Code',               'Robert C. Martin', 'Programming', '9780132350884', 3, 39.50),
('Introduction to Algorithms','Thomas H. Cormen','Computer Science','9780262033848', 2, 89.00),
('Database System Concepts', 'Abraham Silberschatz','Database',  '9780078022159', 4, 72.00),
('Head First PHP & MySQL',   'Lynn Beighley',    'Web Development','9780596006303', 6, 34.99),
('A Brief History of Time',  'Stephen Hawking',  'Science',     '9780553380163', 2, 18.00);
