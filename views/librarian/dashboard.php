<?php
$pageTitle    = 'Librarian dashboard';
$pageHeading  = 'Book catalogue';
$pageSub      = 'Add books, run the issue desk and watch your stock levels';
$isEdit       = !empty($editing);
$headerAction = '<a class="btn btn-ghost" href="index.php?page=librarian&amp;action=export">Download catalogue (CSV)</a>';
require __DIR__ . '/../partials/header.php';
?>

<section class="stat-grid">
    <div class="stat-card"><span class="stat-value"><?= (int)$stats['books'] ?></span><span class="stat-label">Titles</span></div>
    <div class="stat-card"><span class="stat-value"><?= (int)$stats['copies'] ?></span><span class="stat-label">Copies on shelf</span></div>
    <div class="stat-card"><span class="stat-value"><?= (int)$stats['pending'] ?></span><span class="stat-label">Waiting requests</span></div>
    <div class="stat-card"><span class="stat-value"><?= (int)$stats['issued'] ?></span><span class="stat-label">Currently out</span></div>
    <div class="stat-card <?= $stats['overdue'] > 0 ? 'stat-danger' : '' ?>"><span class="stat-value"><?= (int)$stats['overdue'] ?></span><span class="stat-label">Overdue</span></div>
    <div class="stat-card <?= $stats['low_stock'] > 0 ? 'stat-warn' : '' ?>"><span class="stat-value"><?= (int)$stats['low_stock'] ?></span><span class="stat-label">Low stock</span></div>
</section>

<!-- ============ CREATE / UPDATE form ============ -->
<div class="card form-card">
    <h3 class="card-title">
        <?= $isEdit ? 'Edit book #' . (int)$editing['id'] : 'Add a new book' ?>
    </h3>

    <form method="POST" class="form" novalidate onsubmit="return validateForm(this);"
          action="index.php?page=librarian&amp;action=<?= $isEdit ? 'update&amp;id=' . (int)$editing['id'] : 'add' ?>">
        <?php csrf_field(); ?>

        <div class="field-row">
            <div class="field">
                <label for="title">Title</label>
                <input type="text" id="title" name="title" data-label="Title" data-min="2"
                       value="<?= esc($editing['title'] ?? '') ?>"
                       placeholder="e.g. The Pragmatic Programmer" required>
            </div>
            <div class="field">
                <label for="author">Author</label>
                <input type="text" id="author" name="author" data-label="Author" data-min="2"
                       value="<?= esc($editing['author'] ?? '') ?>" placeholder="Author name" required>
            </div>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="category">Category</label>
                <input type="text" id="category" name="category" data-label="Category"
                       value="<?= esc($editing['category'] ?? '') ?>"
                       placeholder="e.g. Programming" required>
            </div>
            <div class="field">
                <label for="isbn">ISBN</label>
                <input type="text" id="isbn" name="isbn" data-label="ISBN" data-min="6"
                       value="<?= esc($editing['isbn'] ?? '') ?>"
                       placeholder="Digits and dashes only" required>
            </div>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="quantity">Copies</label>
                <input type="number" id="quantity" name="quantity" data-label="Copies" min="0" step="1"
                       value="<?= esc($editing['quantity'] ?? '') ?>" placeholder="0" required>
            </div>
            <div class="field">
                <label for="price">Price (<?= esc(CURRENCY) ?>)</label>
                <input type="number" id="price" name="price" data-label="Price" min="0" step="0.01"
                       value="<?= esc($editing['price'] ?? '') ?>" placeholder="0.00" required>
            </div>
        </div>

        <div class="form-actions">
            <?php if ($isEdit): ?>
                <a href="index.php?page=librarian" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary">Save changes</button>
            <?php else: ?>
                <button type="submit" class="btn btn-primary">Add book</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- ============ READ + SEARCH: catalogue ============ -->
<div class="card">
    <div class="card-toolbar">
        <div class="search-wrap">
            <span class="search-icon">&#128269;</span>
            <input type="text" id="bookSearch" class="search-input"
                   placeholder="Search by title, author, category or ISBN...">
        </div>
        <span class="badge" id="bookCount"><?= count($books) ?> titles</span>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th><th>Title</th><th>Author</th><th>Category</th>
                    <th>ISBN</th><th>Copies</th><th>Price</th><th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody id="bookTable">
                <?php if (empty($books)): ?>
                    <tr><td colspan="8" class="empty">The catalogue is empty. Add the first book above.</td></tr>
                <?php else: ?>
                    <?php foreach ($books as $i => $b): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= esc($b['title']) ?></td>
                            <td><?= esc($b['author']) ?></td>
                            <td><?= esc($b['category']) ?></td>
                            <td><?= esc($b['isbn']) ?></td>
                            <td>
                                <span class="pill <?= $b['quantity'] <= LOW_STOCK ? 'pill-suspended' : 'pill-active' ?>">
                                    <?= (int)$b['quantity'] ?>
                                </span>
                            </td>
                            <td><?= esc(money($b['price'])) ?></td>
                            <td class="text-right">
                                <a class="btn-sm btn-edit"
                                   href="index.php?page=librarian&amp;action=edit&amp;id=<?= (int)$b['id'] ?>">Edit</a>
                                <a class="btn-sm btn-delete"
                                   href="<?= esc(csrf_url('index.php?page=librarian&action=delete&id=' . (int)$b['id'])) ?>"
                                   onclick="return confirm('Delete this book?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============ FEATURE 1: issue and return desk ============ -->
<div class="card">
    <h3 class="card-title card-title-pad">Issue and return desk</h3>

    <div class="card-toolbar">
        <div class="search-wrap">
            <span class="search-icon">&#128269;</span>
            <input type="text" id="issueSearch" class="search-input"
                   placeholder="Search requests by book, student or status...">
        </div>
        <span class="badge" id="issueCount"><?= count($requests) ?> requests</span>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th><th>Student</th><th>Book</th><th>Pick-up</th>
                    <th>Due</th><th>Status</th><th>Fine</th><th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody id="issueTable">
                <?php if (empty($requests)): ?>
                    <tr><td colspan="8" class="empty">No student has asked for a book yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($requests as $i => $r): ?>
                        <?php
                            // The fine grows every day a book is late.
                            $liveFine = ($r['status'] === 'issued')
                                ? calculate_fine($r['due_date'])
                                : (float)$r['fine'];
                        ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= esc($r['student_name']) ?><br><span class="sub"><?= esc($r['student_username']) ?></span></td>
                            <td><?= esc($r['title']) ?></td>
                            <td class="nowrap"><?= esc(nice_date($r['pickup_date'])) ?></td>
                            <td class="nowrap"><?= esc(nice_date($r['due_date'])) ?></td>
                            <td><span class="pill pill-<?= esc($r['status']) ?>"><?= esc($r['status']) ?></span></td>
                            <td><?= esc(money($liveFine)) ?></td>
                            <td class="text-right">
                                <?php if ($r['status'] === 'pending'): ?>
                                    <a class="btn-sm btn-ok"
                                       href="<?= esc(csrf_url('index.php?page=librarian&action=issue&id=' . (int)$r['id'])) ?>"
                                       onclick="return confirm('Issue this book for <?= (int)LOAN_DAYS ?> days?');">Issue</a>
                                    <a class="btn-sm btn-delete"
                                       href="<?= esc(csrf_url('index.php?page=librarian&action=reject&id=' . (int)$r['id'])) ?>">Reject</a>
                                <?php elseif ($r['status'] === 'issued'): ?>
                                    <a class="btn-sm btn-edit"
                                       href="<?= esc(csrf_url('index.php?page=librarian&action=return&id=' . (int)$r['id'])) ?>"
                                       onclick="return confirm('Mark this book as returned?');">Return</a>
                                <?php else: ?>
                                    <span class="sub">Closed</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============ FEATURE 2: low stock alerts ============ -->
<div class="card">
    <h3 class="card-title card-title-pad">
        Low stock alert
        <span class="label-hint">&mdash; <?= (int)LOW_STOCK ?> copies or fewer</span>
    </h3>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Title</th><th>Author</th><th>Category</th><th>Copies left</th></tr></thead>
            <tbody id="lowStockTable">
                <?php if (empty($lowStock)): ?>
                    <tr><td colspan="4" class="empty">Every book is well stocked.</td></tr>
                <?php else: ?>
                    <?php foreach ($lowStock as $b): ?>
                        <tr>
                            <td><?= esc($b['title']) ?></td>
                            <td><?= esc($b['author']) ?></td>
                            <td><?= esc($b['category']) ?></td>
                            <td><span class="pill pill-suspended"><?= (int)$b['quantity'] ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
<script>
/* ---------- AJAX 1: catalogue search ---------- */
liveSearch('bookSearch', function () {
    ajaxTable({
        url:     'index.php?page=ajax&action=search_books&q=' +
                 encodeURIComponent(document.getElementById('bookSearch').value.trim()),
        tbody:   'bookTable',
        counter: 'bookCount',
        columns: 8,
        word:    'titles',
        row: function (b, i) {
            var stockClass = (parseInt(b.quantity, 10) <= <?= (int)LOW_STOCK ?>) ? 'pill-suspended' : 'pill-active';
            return '<tr>' +
                '<td>' + (i + 1) + '</td>' +
                '<td>' + esc(b.title) + '</td>' +
                '<td>' + esc(b.author) + '</td>' +
                '<td>' + esc(b.category) + '</td>' +
                '<td>' + esc(b.isbn) + '</td>' +
                '<td><span class="pill ' + stockClass + '">' + esc(b.quantity) + '</span></td>' +
                '<td><?= esc(CURRENCY) ?>' + parseFloat(b.price).toFixed(2) + '</td>' +
                '<td class="text-right">' +
                    '<a class="btn-sm btn-edit" href="index.php?page=librarian&action=edit&id=' + b.id + '">Edit</a>' +
                    '<a class="btn-sm btn-delete" href="index.php?page=librarian&action=delete&id=' + b.id +
                    '&csrf_token=<?= csrf_token() ?>" onclick="return confirm(\'Delete this book?\');">Delete</a>' +
                '</td>' +
            '</tr>';
        }
    });
});

/* ---------- AJAX 2: issue desk search ---------- */
liveSearch('issueSearch', function () {
    ajaxTable({
        url:     'index.php?page=ajax&action=search_issues&q=' +
                 encodeURIComponent(document.getElementById('issueSearch').value.trim()),
        tbody:   'issueTable',
        counter: 'issueCount',
        columns: 8,
        word:    'requests',
        row: function (r, i) {
            var token   = '&csrf_token=<?= csrf_token() ?>';
            var actions = '<span class="sub">Closed</span>';

            if (r.status === 'pending') {
                actions =
                    '<a class="btn-sm btn-ok" href="index.php?page=librarian&action=issue&id=' + r.id + token +
                    '" onclick="return confirm(\'Issue this book?\');">Issue</a>' +
                    '<a class="btn-sm btn-delete" href="index.php?page=librarian&action=reject&id=' + r.id + token + '">Reject</a>';
            } else if (r.status === 'issued') {
                actions =
                    '<a class="btn-sm btn-edit" href="index.php?page=librarian&action=return&id=' + r.id + token +
                    '" onclick="return confirm(\'Mark this book as returned?\');">Return</a>';
            }

            return '<tr>' +
                '<td>' + (i + 1) + '</td>' +
                '<td>' + esc(r.student_name) + '<br><span class="sub">' + esc(r.student_username) + '</span></td>' +
                '<td>' + esc(r.title) + '</td>' +
                '<td class="nowrap">' + esc(r.pickup_date) + '</td>' +
                '<td class="nowrap">' + esc(r.due_date || '-') + '</td>' +
                '<td><span class="pill pill-' + esc(r.status) + '">' + esc(r.status) + '</span></td>' +
                '<td><?= esc(CURRENCY) ?>' + liveFine(r).toFixed(2) + '</td>' +
                '<td class="text-right">' + actions + '</td>' +
            '</tr>';
        }
    });
});
</script>
</body>
</html>
