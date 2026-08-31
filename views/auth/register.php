<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Create an account &mdash; <?= esc(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">

<div class="auth-shell">
    <div class="auth-side">
        <div class="logo-big">&#128218;</div>
        <h1>Create your account</h1>
        <p>Pick the account type that matches how you use the library.</p>
        <ul class="feature-list">
            <li>&#10003; <strong>Student</strong> &mdash; borrow books and track due dates</li>
            <li>&#10003; <strong>Librarian</strong> &mdash; run the catalogue and issue desk</li>
            <li>&#10003; <strong>Visitor</strong> &mdash; book day passes and suggest books</li>
        </ul>
        <p class="side-note">Administrator accounts are created by an existing admin.</p>
    </div>

    <div class="auth-form-wrap">
        <div class="auth-card">
            <h2>Sign up</h2>
            <p class="muted">It takes less than a minute</p>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?= esc($error) ?></div>
            <?php endif; ?>
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?= esc($success) ?></div>
            <?php endif; ?>

            <form method="POST" action="index.php?page=register" class="form"
                  novalidate onsubmit="return validateForm(this);">
                <?php csrf_field(); ?>

                <div class="field">
                    <label for="role">Account type</label>
                    <select id="role" name="role" data-label="Account type" required>
                        <option value="student"   <?= $old['role'] === 'student'   ? 'selected' : '' ?>>Student</option>
                        <option value="librarian" <?= $old['role'] === 'librarian' ? 'selected' : '' ?>>Librarian</option>
                        <option value="visitor"   <?= $old['role'] === 'visitor'   ? 'selected' : '' ?>>Visitor</option>
                    </select>
                </div>

                <div class="field">
                    <label for="name">Full name</label>
                    <input type="text" id="name" name="name" data-label="Full name" data-min="3"
                           value="<?= esc($old['name']) ?>" placeholder="e.g. Rafiq Hasan" required>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" data-label="Email"
                               value="<?= esc($old['email']) ?>" placeholder="you@example.com" required>
                    </div>
                    <div class="field">
                        <label for="contact">Contact number</label>
                        <input type="text" id="contact" name="contact" data-label="Contact number" data-phone="1"
                               value="<?= esc($old['contact']) ?>" placeholder="+880 1XXXXXXXXX" required>
                    </div>
                </div>

                <div class="field">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" data-label="Username" data-min="4"
                           value="<?= esc($old['username']) ?>" placeholder="4-20 letters or numbers" required>
                    <!-- AJAX: filled in live by the script below -->
                    <span id="usernameNote" class="field-note"></span>
                </div>

                <div class="field-row">
                    <div class="field">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" data-label="Password" data-min="6"
                               placeholder="At least 6 characters" required>
                    </div>
                    <div class="field">
                        <label for="confirm_password">Repeat password</label>
                        <input type="password" id="confirm_password" name="confirm_password"
                               data-label="Repeat password" data-match="password"
                               placeholder="Type it again" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block">Create account</button>
            </form>

            <p class="auth-foot">
                Already have an account? <a href="index.php?page=login">Sign in</a>
            </p>
        </div>
    </div>
</div>

<script src="assets/js/app.js"></script>
<script>
// AJAX: ask the server whether the typed username is still free.
(function () {
    var input = document.getElementById('username');
    var note  = document.getElementById('usernameNote');
    var timer;

    input.addEventListener('input', function () {
        clearTimeout(timer);
        var value = input.value.trim();
        if (value === '') { note.textContent = ''; note.className = 'field-note'; return; }

        timer = setTimeout(function () {
            fetch('index.php?page=ajax&action=check_username&username=' + encodeURIComponent(value))
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    note.textContent = data.message;
                    note.className = 'field-note ' + (data.ok ? 'note-ok' : 'note-bad');
                })
                .catch(function () { note.textContent = ''; });
        }, 300);
    });
})();
</script>
</body>
</html>
