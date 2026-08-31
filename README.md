# Library Management System (PHP + MySQL, MVC)

A teaching project for a 4-role library system: **admin, librarian, student, visitor**.
Written in plain PHP with procedural `mysqli` and prepared statements. No frameworks,
no Composer, no build step. Copy it into XAMPP and it runs.

---

## 1. Install (XAMPP)

1. Copy the `library_management` folder into `C:\xampp\htdocs\`
   so it becomes `htdocs/library_management/`.
2. Start **Apache** and **MySQL** in the XAMPP control panel.
3. Open `http://localhost/phpmyadmin` → **Import** → choose `database.sql` → **Go**.
4. Open `http://localhost/library_management/`.
5. Sign in as the default admin: **admin / admin123**

The admin account is created automatically the first time a page loads
(see the bottom of `config/config.php`). Everyone else signs up on the register page.

If your MySQL uses a password, change `DB_PASS` in `config/config.php`.

---

## 2. Folder structure

```
library_management/
├── index.php                  Front controller: the ONLY entry point (router)
├── database.sql               Schema + a few sample books
├── README.md
│
├── config/
│   └── config.php             DB connection, session settings, app constants
│
├── helpers/
│   └── helpers.php            esc(), CSRF, login guards, flash messages, fines
│
├── models/                    M — every SQL query lives here
│   ├── user_model.php         all 4 roles (one users table)
│   ├── book_model.php         catalogue + stock
│   ├── borrow_model.php       borrow requests + issue desk
│   ├── visitor_model.php      passes, membership, suggestions
│   └── log_model.php          activity log
│
├── controllers/               C — request handling, validation, decisions
│   ├── auth_controller.php    login / register / logout
│   ├── admin_controller.php
│   ├── librarian_controller.php
│   ├── student_controller.php
│   ├── visitor_controller.php
│   └── ajax_controller.php    all JSON endpoints
│
├── views/                     V — HTML only
│   ├── partials/              header.php, footer.php (shared layout)
│   ├── auth/                  login.php, register.php
│   ├── admin/dashboard.php
│   ├── librarian/dashboard.php
│   ├── student/dashboard.php
│   └── visitor/dashboard.php
│
└── assets/
    ├── css/style.css
    └── js/app.js              validation, escaping, live search, AJAX tables
```

**The MVC rule used throughout:** a view never runs a query, and a model never
prints HTML. The controller sits in the middle: it reads `$_POST`, validates,
calls the model, then `require`s the view.

---

## 3. How the router works

Every URL looks like this:

```
index.php?page=<dashboard>&action=<what to do>&id=<row id>
```

| URL | What happens |
| --- | --- |
| `index.php?page=login` | Login page |
| `index.php?page=register` | Signup page |
| `index.php?page=admin` | Admin dashboard (list mode) |
| `index.php?page=librarian&action=edit&id=4` | Load book 4 into the form |
| `index.php?page=student&action=delete&id=7&csrf_token=…` | Cancel request 7 |
| `index.php?page=ajax&action=search_books&q=php` | Returns JSON |
| `index.php?page=logout` | Sign out |

`index.php` loads config → helpers → models → controllers, checks the session
timeout, then sends the request to one controller. `require_role('admin')` blocks
anyone who is not an admin before the controller even starts.

---

## 4. The four roles

Each role owns one table and does full **Create, Read, Update, Delete and Search**
on its own dashboard. The form sits at the top of the page; the searchable table
sits below it. Clicking **Edit** reloads the same page with the row loaded into
that same form.

| Role | Manages (CRUD) | Feature 1 | Feature 2 | Feature 3 |
| --- | --- | --- | --- | --- |
| **Admin** | User accounts (all roles) | Suspend / activate accounts, approve membership upgrades | System-wide activity log with search | Live statistics panel (AJAX, refreshes every 15s) |
| **Librarian** | Books | Issue & return desk — stock and fines update automatically | Low stock alert list | Download the catalogue as a CSV file |
| **Student** | My borrow requests | Catalogue browser showing live availability | Due-date and fine tracker | Printable digital library card |
| **Visitor** | My visit passes | Apply for a student membership upgrade | Book suggestion box | Printable day pass with a unique code |

No feature appears on two dashboards.

### How the roles connect

- A **student** requests a book → the **librarian** sees it on the issue desk.
- The librarian clicks **Issue** → stock drops by 1, a due date is set (14 days).
- The librarian clicks **Return** → stock goes back up, the fine is worked out
  (5 per day late) and stored.
- A **visitor** applies for membership → the **admin** approves it → that visitor
  becomes a student and gets the student dashboard on the next sign-in.

---

## 5. Requirement checklist

| Requirement | Where to look |
| --- | --- |
| **MVC** | `models/`, `controllers/`, `views/`, routed by `index.php` |
| **DB (MySQLi procedural)** | every function in `models/` uses `mysqli_prepare` |
| **Auth (session + cookie)** | `controllers/auth_controller.php`, `helpers/helpers.php` |
| **PHP validation** | the `if / elseif` chain at the top of every controller action |
| **JS validation** | `validateForm()` in `assets/js/app.js`, called by `onsubmit` |
| **AJAX / JSON** | `controllers/ajax_controller.php` + `ajaxTable()` in `app.js` |
| **UI (HTML/CSS)** | `views/`, `assets/css/style.css` |
| **Basic web security** | see section 6 |
| **Feature completeness** | CRUD + search + 3 features per role |

---

## 6. Security, and why each piece is there

| Attack | Defence | File |
| --- | --- | --- |
| SQL injection | Prepared statements everywhere — user text is never glued into SQL | all `models/` |
| Stolen passwords | `password_hash()` on save, `password_verify()` on login | `user_model.php` |
| XSS (server) | `esc()` wraps every value printed into HTML | `helpers.php`, all views |
| XSS (client) | `esc()` in JavaScript before any AJAX row is inserted | `app.js` |
| CSRF | A secret token in every POST form and every delete/issue link | `helpers.php`, all views |
| Session fixation | `session_regenerate_id(true)` right after a successful login | `auth_controller.php` |
| Cookie theft | `httponly` + `samesite=Lax` on the session cookie | `config.php` |
| Idle machines | Automatic sign-out after 30 minutes | `check_session_timeout()` |
| Wrong role | `require_role()` before the controller; each AJAX action re-checks | `index.php`, `ajax_controller.php` |
| URL tampering | A student can only load their own rows (`WHERE … AND student_id = ?`) | `borrow_model.php`, `visitor_model.php` |
| Username guessing | Wrong username and wrong password give the same message | `auth_controller.php` |
| Self-lockout | An admin cannot delete, suspend or demote themselves | `admin_controller.php` |

Two things worth saying out loud to students:

1. **JavaScript validation is a convenience, not a defence.** Anyone can turn
   JavaScript off. That is why every controller repeats the checks in PHP.
2. **"Remember me" only refills the username**, never the password.

---

## 7. Settings you can change

All in `config/config.php`:

```php
define('LOAN_DAYS',    14);   // how long a student may keep a book
define('FINE_PER_DAY', 5);    // fine for each day past the due date
define('LOW_STOCK',    3);    // a book at or below this triggers the alert
define('CURRENCY',     '$');  // symbol shown next to prices
define('SESSION_TIMEOUT', 1800); // idle sign-out, in seconds
```

---

## 8. Test accounts

| Role | Username | Password |
| --- | --- | --- |
| Admin | `admin` | `admin123` |
| Student | sign up on the register page | |
| Librarian | sign up on the register page | |
| Visitor | sign up on the register page | |

Nobody can sign up as an admin — the register page only accepts the other three
roles, and the controller checks that list again on the server. New admins are
created by an existing admin.

---
 "Copyright (c) [2026] [Wahidul Alam Riyad]. All rights reserved."
---
