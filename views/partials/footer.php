</main>

<footer class="footer">
    &copy; <?= date('Y') ?> <?= esc(APP_NAME) ?> &mdash; Library Management System
</footer>

<script>
    // Settings from config.php that the JavaScript needs.
    window.FINE_RATE = <?= (int)FINE_PER_DAY ?>;
</script>
<script src="assets/js/app.js"></script>
