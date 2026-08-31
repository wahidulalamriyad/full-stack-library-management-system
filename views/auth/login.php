<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Sign in &mdash; <?= esc(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="auth-body">

<div class="auth-shell">
    <!-- Left panel -->
    <div class="auth-side">
        <div class="logo-big">&#128218;</div>
        <h1>Library Management System</h1>
        <p>One place for staff, students and visitors. Sign in to open your dashboard.</p>
        <ul class="feature-list">
            <li>&#10003; Admin &mdash; accounts, approvals and the activity log</li>
            <li>&#10003; Librarian &mdash; catalogue, issue desk and stock alerts</li>
            <li>&#10003; Student &mdash; borrow books, track due dates and fines</li>
            <li>&#10003; Visitor &mdash; day passes, membership and suggestions</li>
        </ul>
    </div>

    <!-- Right panel: the form -->
    <div class="auth-form-wrap">
        <div class="auth-card">
            <h2>Welcome back</h2>
            <p class="muted">Sign in to continue</p>

            <?php foreach (get_flash() as $flash): ?>
                <div class="alert alert-<?= esc($flash['type']) ?>"><?= esc($flash['message']) ?></div>
            <?php endforeach; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?= esc($error) ?></div>
            <?php endif; ?>

            <!-- onsubmit runs the JavaScript validation in assets/js/app.js -->
            <form method="POST" action="index.php?page=login" class="form"
                  novalidate onsubmit="return validateForm(this);">
                <?php csrf_field(); ?>

                <div class="field">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" data-label="Username"
                           value="<?= esc($prefill ?? '') ?>"
                           placeholder="Your username" required autofocus>
                </div>

                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" data-label="Password"
                           placeholder="Your password" required>
                </div>

                <label class="checkbox">
                    <input type="checkbox" name="remember" <?= !empty($prefill) ? 'checked' : '' ?>>
                    <span>Remember my username on this computer</span>
                </label>

                <button type="submit" class="btn btn-primary btn-block">Sign in</button>
            </form>

            <p class="auth-foot">
                New here? <a href="index.php?page=register">Create an account</a>
            </p>
            <p class="hint"><strong>Default admin:</strong> admin / admin123</p>
        </div>
    </div>
</div>

<script src="assets/js/app.js"></script>
</body>
</html>
