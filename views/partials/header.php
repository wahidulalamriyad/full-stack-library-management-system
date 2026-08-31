<?php
// Shared top of every dashboard page.
// The view that includes this must set: $pageTitle, $pageHeading, $pageSub
$navUser = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= esc($pageTitle ?? APP_NAME) ?> &mdash; <?= esc(APP_NAME) ?></title>
<link rel="stylesheet" href="assets/css/style.css">
</head>
<body class="app-body">

<header class="navbar">
    <div class="navbar-inner">
        <a class="brand" href="index.php?page=<?= esc($navUser['role']) ?>">
            <span class="brand-icon">&#128218;</span>
            <span><?= esc(APP_NAME) ?></span>
        </a>
        <div class="nav-user">
            <span class="user-pill">
                <span class="user-avatar"><?= esc(strtoupper(substr($navUser['name'], 0, 1))) ?></span>
                <span class="user-meta">
                    <span class="user-name"><?= esc($navUser['name']) ?></span>
                    <span class="user-role"><?= esc(role_label($navUser['role'])) ?></span>
                </span>
            </span>
            <a href="index.php?page=logout" class="btn-logout">Sign out</a>
        </div>
    </div>
</header>

<main class="main-content">
    <div class="page-header">
        <div>
            <h1 class="page-title"><?= esc($pageHeading ?? '') ?></h1>
            <p class="page-sub"><?= esc($pageSub ?? '') ?></p>
        </div>
        <?php if (!empty($headerAction)): ?>
            <div><?= $headerAction ?></div>
        <?php endif; ?>
    </div>

    <?php foreach (get_flash() as $flash): ?>
        <div class="alert alert-<?= esc($flash['type']) ?>"><?= esc($flash['message']) ?></div>
    <?php endforeach; ?>

    <?php if (!empty($error)): ?>
        <div class="alert alert-error"><?= esc($error) ?></div>
    <?php endif; ?>
