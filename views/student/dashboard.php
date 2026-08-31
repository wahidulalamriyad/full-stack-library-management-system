<?php
$pageTitle   = 'Student dashboard';
$pageHeading = 'My borrowing';
$pageSub     = 'Request books, follow your due dates and keep an eye on fines';
$isEdit      = !empty($editing);
$me          = current_user();
require __DIR__ . '/../partials/header.php';
?>

<!-- ============ FEATURE 2: due date and fine tracker ============ -->
<section class="stat-grid">
    <div class="stat-card"><span class="stat-value"><?= (int)$stats['pending'] ?></span><span class="stat-label">Waiting</span></div>
    <div class="stat-card"><span class="stat-value"><?= (int)$stats['issued'] ?></span><span class="stat-label">With me now</span></div>
    <div class="stat-card"><span class="stat-value"><?= (int)$stats['returned'] ?></span><span class="stat-label">Returned</span></div>
    <div class="stat-card <?= $stats['overdue'] > 0 ? 'stat-danger' : '' ?>"><span class="stat-value"><?= (int)$stats['overdue'] ?></span><span class="stat-label">Overdue</span></div>
    <div class="stat-card <?= $liveFine > 0 ? 'stat-danger' : '' ?>"><span class="stat-value"><?= esc(money($liveFine)) ?></span><span class="stat-label">Fine building up</span></div>
    <div class="stat-card"><span class="stat-value"><?= esc(money($stats['paid_fine'])) ?></span><span class="stat-label">Fines so far</span></div>
</section>

<div class="two-col">
    <!-- ============ CREATE / UPDATE: borrow request ============ -->
    <div class="card form-card">
        <h3 class="card-title">
            <?= $isEdit ? 'Edit request #' . (int)$editing['id'] : 'Request a book' ?>
        </h3>

        <form method="POST" class="form" novalidate onsubmit="return validateForm(this);"
              action="index.php?page=student&amp;action=<?= $isEdit ? 'update&amp;id=' . (int)$editing['id'] : 'add' ?>">
            <?php csrf_field(); ?>

            <div class="field">
                <label for="book_id">Book</label>
                <select id="book_id" name="book_id" data-label="Book" required>
                    <option value="">Choose a book...</option>
                    <?php foreach ($available as $b): ?>
                        <option value="<?= (int)$b['id'] ?>"
                            <?= ((int)($editing['book_id'] ?? 0) === (int)$b['id']) ? 'selected' : '' ?>>
                            <?= esc($b['title']) ?> &mdash; <?= esc($b['author']) ?> (<?= (int)$b['quantity'] ?> left)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="field">
                <label for="pickup_date">Pick-up date</label>
                <input type="date" id="pickup_date" name="pickup_date" data-label="Pick-up date"
                       min="<?= date('Y-m-d') ?>"
                       value="<?= esc($editing['pickup_date'] ?? date('Y-m-d')) ?>" required>
            </div>

            <div class="field">
                <label for="notes">Note for the librarian <span class="label-hint">(optional)</span></label>
                <input type="text" id="notes" name="notes" data-label="Note"
                       value="<?= esc($editing['notes'] ?? '') ?>"
                       placeholder="e.g. Needed for the CSE 3rd year project">
            </div>

            <div class="form-actions">
                <?php if ($isEdit): ?>
                    <a href="index.php?page=student" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                <?php else: ?>
                    <button type="submit" class="btn btn-primary">Send request</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ============ FEATURE 3: printable digital library card ============ -->
    <div class="card form-card">
        <h3 class="card-title">My library card</h3>

        <div class="library-card" id="libraryCard">
            <div class="lib-card-top">
                <span class="lib-card-brand"><?= esc(APP_NAME) ?></span>
                <span class="lib-card-type">Student</span>
            </div>
            <div class="lib-card-name"><?= esc($me['name']) ?></div>
            <div class="lib-card-rows">
                <span>Card no.</span><strong>STU-<?= str_pad((string)(int)$me['id'], 5, '0', STR_PAD_LEFT) ?></strong>
                <span>Username</span><strong><?= esc($me['username']) ?></strong>
                <span>Member since</span><strong><?= esc(nice_date($me['created_at'])) ?></strong>
                <span>Loan period</span><strong><?= (int)LOAN_DAYS ?> days</strong>
            </div>
            <div class="lib-card-foot">Fine after the due date: <?= esc(money(FINE_PER_DAY)) ?> per day</div>
        </div>

        <div class="form-actions">
            <button type="button" class="btn btn-ghost" onclick="window.print();">Print my card</button>
        </div>
    </div>
</div>

<!-- ============ READ + SEARCH: my requests ============ -->
<div class="card">
    <div class="card-toolbar">
        <div class="search-wrap">
            <span class="search-icon">&#128269;</span>
            <input type="text" id="requestSearch" class="search-input"
                   placeholder="Search my requests by book, author or status...">
        </div>
        <span class="badge" id="requestCount"><?= count($requests) ?> requests</span>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th><th>Book</th><th>Pick-up</th><th>Due date</th>
                    <th>Status</th><th>Fine</th><th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody id="requestTable">
                <?php if (empty($requests)): ?>
                    <tr><td colspan="7" class="empty">You have not requested a book yet. Use the form above.</td></tr>
                <?php else: ?>
                    <?php foreach ($requests as $i => $r): ?>
                        <?php
                            $late = ($r['status'] === 'issued') ? days_late($r['due_date']) : 0;
                            $fine = ($r['status'] === 'issued') ? calculate_fine($r['due_date']) : (float)$r['fine'];
                        ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= esc($r['title']) ?><br><span class="sub"><?= esc($r['author']) ?></span></td>
                            <td class="nowrap"><?= esc(nice_date($r['pickup_date'])) ?></td>
                            <td class="nowrap">
                                <?= esc(nice_date($r['due_date'])) ?>
                                <?php if ($r['status'] === 'issued'): ?>
                                    <br>
                                    <span class="sub <?= $late > 0 ? 'text-danger' : '' ?>">
                                        <?= $late > 0 ? $late . ' days late' : abs($late) . ' days left' ?>
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><span class="pill pill-<?= esc($r['status']) ?>"><?= esc($r['status']) ?></span></td>
                            <td><?= esc(money($fine)) ?></td>
                            <td class="text-right">
                                <?php if ($r['status'] === 'pending'): ?>
                                    <a class="btn-sm btn-edit"
                                       href="index.php?page=student&amp;action=edit&amp;id=<?= (int)$r['id'] ?>">Edit</a>
                                    <a class="btn-sm btn-delete"
                                       href="<?= esc(csrf_url('index.php?page=student&action=delete&id=' . (int)$r['id'])) ?>"
                                       onclick="return confirm('Cancel this request?');">Cancel</a>
                                <?php else: ?>
                                    <span class="sub">No action</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============ FEATURE 1: catalogue browser ============ -->
<div class="card">
    <h3 class="card-title card-title-pad">Library catalogue</h3>

    <div class="card-toolbar">
        <div class="search-wrap">
            <span class="search-icon">&#128269;</span>
            <input type="text" id="catalogSearch" class="search-input"
                   placeholder="Search the whole catalogue...">
        </div>
        <span class="badge" id="catalogCount"><?= count($books) ?> titles</span>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>Title</th><th>Author</th><th>Category</th><th>Availability</th><th class="text-right">Action</th></tr>
            </thead>
            <tbody id="catalogTable">
                <?php if (empty($books)): ?>
                    <tr><td colspan="5" class="empty">The catalogue is empty right now.</td></tr>
                <?php else: ?>
                    <?php foreach ($books as $b): ?>
                        <tr>
                            <td><?= esc($b['title']) ?></td>
                            <td><?= esc($b['author']) ?></td>
                            <td><?= esc($b['category']) ?></td>
                            <td>
                                <?php if ((int)$b['quantity'] > 0): ?>
                                    <span class="pill pill-active"><?= (int)$b['quantity'] ?> on shelf</span>
                                <?php else: ?>
                                    <span class="pill pill-suspended">All copies out</span>
                                <?php endif; ?>
                            </td>
                            <td class="text-right">
                                <?php if ((int)$b['quantity'] > 0): ?>
                                    <button type="button" class="btn-sm btn-edit"
                                            onclick="pickBook(<?= (int)$b['id'] ?>)">Request</button>
                                <?php else: ?>
                                    <span class="sub">Unavailable</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
<script>
/* Clicking "Request" in the catalogue selects that book in the form above. */
function pickBook(id) {
    var select = document.getElementById('book_id');
    select.value = String(id);
    if (select.value === '') {
        alert('That book cannot be requested right now.');
        return;
    }
    select.scrollIntoView({ behavior: 'smooth', block: 'center' });
    select.focus();
}

/* ---------- AJAX 1: search my own requests ---------- */
liveSearch('requestSearch', function () {
    ajaxTable({
        url:     'index.php?page=ajax&action=search_requests&q=' +
                 encodeURIComponent(document.getElementById('requestSearch').value.trim()),
        tbody:   'requestTable',
        counter: 'requestCount',
        columns: 7,
        word:    'requests',
        row: function (r, i) {
            var actions = '<span class="sub">No action</span>';
            if (r.status === 'pending') {
                actions =
                    '<a class="btn-sm btn-edit" href="index.php?page=student&action=edit&id=' + r.id + '">Edit</a>' +
                    '<a class="btn-sm btn-delete" href="index.php?page=student&action=delete&id=' + r.id +
                    '&csrf_token=<?= csrf_token() ?>" onclick="return confirm(\'Cancel this request?\');">Cancel</a>';
            }
            return '<tr>' +
                '<td>' + (i + 1) + '</td>' +
                '<td>' + esc(r.title) + '<br><span class="sub">' + esc(r.author) + '</span></td>' +
                '<td class="nowrap">' + esc(r.pickup_date) + '</td>' +
                '<td class="nowrap">' + esc(r.due_date || '-') + '</td>' +
                '<td><span class="pill pill-' + esc(r.status) + '">' + esc(r.status) + '</span></td>' +
                '<td><?= esc(CURRENCY) ?>' + liveFine(r).toFixed(2) + '</td>' +
                '<td class="text-right">' + actions + '</td>' +
            '</tr>';
        }
    });
});

/* ---------- AJAX 2: search the catalogue ---------- */
liveSearch('catalogSearch', function () {
    ajaxTable({
        url:     'index.php?page=ajax&action=search_books&q=' +
                 encodeURIComponent(document.getElementById('catalogSearch').value.trim()),
        tbody:   'catalogTable',
        counter: 'catalogCount',
        columns: 5,
        word:    'titles',
        row: function (b) {
            var left = parseInt(b.quantity, 10);
            var availability = (left > 0)
                ? '<span class="pill pill-active">' + left + ' on shelf</span>'
                : '<span class="pill pill-suspended">All copies out</span>';
            var action = (left > 0)
                ? '<button type="button" class="btn-sm btn-edit" onclick="pickBook(' + b.id + ')">Request</button>'
                : '<span class="sub">Unavailable</span>';

            return '<tr>' +
                '<td>' + esc(b.title) + '</td>' +
                '<td>' + esc(b.author) + '</td>' +
                '<td>' + esc(b.category) + '</td>' +
                '<td>' + availability + '</td>' +
                '<td class="text-right">' + action + '</td>' +
            '</tr>';
        }
    });
});
</script>
</body>
</html>
