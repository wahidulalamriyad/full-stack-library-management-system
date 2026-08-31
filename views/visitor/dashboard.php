<?php
$pageTitle   = 'Visitor dashboard';
$pageHeading = 'My library visits';
$pageSub     = 'Book a day pass, apply for membership and suggest books to buy';
$isEdit      = !empty($editing);
$me          = current_user();

// The newest pass that is still valid, shown as the printable pass below.
$activePass = null;
foreach ($passes as $p) {
    if ($p['status'] === 'booked' && strtotime($p['visit_date']) >= strtotime(date('Y-m-d'))) {
        $activePass = $p;
        break;
    }
}
require __DIR__ . '/../partials/header.php';
?>

<section class="stat-grid">
    <div class="stat-card"><span class="stat-value"><?= (int)$stats['booked'] ?></span><span class="stat-label">Passes booked</span></div>
    <div class="stat-card"><span class="stat-value"><?= (int)$stats['upcoming'] ?></span><span class="stat-label">Still upcoming</span></div>
    <div class="stat-card"><span class="stat-value"><?= (int)$stats['suggestions'] ?></span><span class="stat-label">Books suggested</span></div>
    <div class="stat-card">
        <span class="stat-value stat-text">
            <?= $application ? esc(ucfirst($application['status'])) : 'None' ?>
        </span>
        <span class="stat-label">Membership</span>
    </div>
</section>

<div class="two-col">
    <!-- ============ CREATE / UPDATE: visit pass ============ -->
    <div class="card form-card">
        <h3 class="card-title">
            <?= $isEdit ? 'Edit pass #' . (int)$editing['id'] : 'Book a visit' ?>
        </h3>

        <form method="POST" class="form" novalidate onsubmit="return validateForm(this);"
              action="index.php?page=visitor&amp;action=<?= $isEdit ? 'update&amp;id=' . (int)$editing['id'] : 'add' ?>">
            <?php csrf_field(); ?>

            <div class="field">
                <label for="visit_date">Visit date</label>
                <input type="date" id="visit_date" name="visit_date" data-label="Visit date"
                       min="<?= date('Y-m-d') ?>"
                       value="<?= esc($editing['visit_date'] ?? date('Y-m-d')) ?>" required>
            </div>

            <div class="field">
                <label for="purpose">Purpose of the visit</label>
                <input type="text" id="purpose" name="purpose" data-label="Purpose" data-min="3"
                       value="<?= esc($editing['purpose'] ?? '') ?>"
                       placeholder="e.g. Reading room and newspaper archive" required>
            </div>

            <div class="field-row">
                <div class="field">
                    <label for="guests">People coming</label>
                    <input type="number" id="guests" name="guests" data-label="People coming"
                           min="1" max="10" step="1"
                           value="<?= esc($editing['guests'] ?? 1) ?>" required>
                </div>
                <?php if ($isEdit): ?>
                    <div class="field">
                        <label for="status">Status</label>
                        <select id="status" name="status" data-label="Status" required>
                            <option value="booked"    <?= (($editing['status'] ?? '') === 'booked')    ? 'selected' : '' ?>>Booked</option>
                            <option value="cancelled" <?= (($editing['status'] ?? '') === 'cancelled') ? 'selected' : '' ?>>Cancelled</option>
                        </select>
                    </div>
                <?php endif; ?>
            </div>

            <div class="form-actions">
                <?php if ($isEdit): ?>
                    <a href="index.php?page=visitor" class="btn btn-ghost">Cancel</a>
                    <button type="submit" class="btn btn-primary">Save changes</button>
                <?php else: ?>
                    <button type="submit" class="btn btn-primary">Book the pass</button>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <!-- ============ FEATURE 3: printable day pass ============ -->
    <div class="card form-card">
        <h3 class="card-title">My day pass</h3>

        <?php if ($activePass): ?>
            <div class="library-card pass-card">
                <div class="lib-card-top">
                    <span class="lib-card-brand"><?= esc(APP_NAME) ?></span>
                    <span class="lib-card-type">Day pass</span>
                </div>
                <div class="pass-code"><?= esc($activePass['pass_code']) ?></div>
                <div class="lib-card-rows">
                    <span>Name</span><strong><?= esc($me['name']) ?></strong>
                    <span>Visit date</span><strong><?= esc(nice_date($activePass['visit_date'])) ?></strong>
                    <span>People</span><strong><?= (int)$activePass['guests'] ?></strong>
                    <span>Purpose</span><strong><?= esc($activePass['purpose']) ?></strong>
                </div>
                <div class="lib-card-foot">Show this code at the front desk.</div>
            </div>
            <div class="form-actions">
                <button type="button" class="btn btn-ghost" onclick="window.print();">Print this pass</button>
            </div>
        <?php else: ?>
            <p class="muted">Book a visit on the left and your pass code will appear here.</p>
        <?php endif; ?>
    </div>
</div>

<!-- ============ READ + SEARCH: my passes ============ -->
<div class="card">
    <div class="card-toolbar">
        <div class="search-wrap">
            <span class="search-icon">&#128269;</span>
            <input type="text" id="passSearch" class="search-input"
                   placeholder="Search my passes by date, code or purpose...">
        </div>
        <span class="badge" id="passCount"><?= count($passes) ?> passes</span>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>#</th><th>Pass code</th><th>Visit date</th><th>Purpose</th>
                    <th>People</th><th>Status</th><th class="text-right">Actions</th></tr>
            </thead>
            <tbody id="passTable">
                <?php if (empty($passes)): ?>
                    <tr><td colspan="7" class="empty">You have not booked a visit yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($passes as $i => $p): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td class="nowrap"><strong><?= esc($p['pass_code']) ?></strong></td>
                            <td class="nowrap"><?= esc(nice_date($p['visit_date'])) ?></td>
                            <td><?= esc($p['purpose']) ?></td>
                            <td><?= (int)$p['guests'] ?></td>
                            <td><span class="pill pill-<?= $p['status'] === 'booked' ? 'active' : 'rejected' ?>"><?= esc($p['status']) ?></span></td>
                            <td class="text-right">
                                <a class="btn-sm btn-edit"
                                   href="index.php?page=visitor&amp;action=edit&amp;id=<?= (int)$p['id'] ?>">Edit</a>
                                <a class="btn-sm btn-delete"
                                   href="<?= esc(csrf_url('index.php?page=visitor&action=delete&id=' . (int)$p['id'])) ?>"
                                   onclick="return confirm('Delete this pass?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<div class="two-col">
    <!-- ============ FEATURE 1: membership application ============ -->
    <div class="card form-card">
        <h3 class="card-title">Become a student member</h3>

        <?php if ($application && $application['status'] === 'pending'): ?>
            <p class="muted">Your application is with the administrator.</p>
            <div class="info-box">
                <span class="pill pill-pending">Pending</span>
                <p><?= esc($application['reason']) ?></p>
                <span class="sub">Sent <?= esc(nice_date($application['applied_at'])) ?></span>
            </div>
        <?php elseif ($application && $application['status'] === 'approved'): ?>
            <div class="info-box">
                <span class="pill pill-active">Approved</span>
                <p>Sign out and sign back in to open your student dashboard.</p>
            </div>
        <?php else: ?>
            <?php if ($application && $application['status'] === 'rejected'): ?>
                <div class="info-box">
                    <span class="pill pill-rejected">Rejected</span>
                    <p>Your last application was turned down. You may apply again.</p>
                </div>
            <?php endif; ?>
            <p class="muted">Members can borrow books and take them home.</p>
            <form method="POST" action="index.php?page=visitor&amp;action=apply" class="form"
                  novalidate onsubmit="return validateForm(this);">
                <?php csrf_field(); ?>
                <div class="field">
                    <label for="reason">Why do you want to join?</label>
                    <input type="text" id="reason" name="reason" data-label="Reason" data-min="10"
                           placeholder="At least 10 characters" required>
                </div>
                <button type="submit" class="btn btn-primary btn-block">Send application</button>
            </form>
        <?php endif; ?>
    </div>

    <!-- ============ FEATURE 2: book suggestion box ============ -->
    <div class="card form-card">
        <h3 class="card-title">Suggest a book to buy</h3>

        <form method="POST" action="index.php?page=visitor&amp;action=suggest" class="form"
              novalidate onsubmit="return validateForm(this);">
            <?php csrf_field(); ?>
            <div class="field-row">
                <div class="field">
                    <label for="s_title">Title</label>
                    <input type="text" id="s_title" name="s_title" data-label="Title" data-min="2"
                           placeholder="Book title" required>
                </div>
                <div class="field">
                    <label for="s_author">Author</label>
                    <input type="text" id="s_author" name="s_author" data-label="Author" data-min="2"
                           placeholder="Author name" required>
                </div>
            </div>
            <div class="field">
                <label for="s_reason">Why this book? <span class="label-hint">(optional)</span></label>
                <input type="text" id="s_reason" name="s_reason" data-label="Reason"
                       placeholder="e.g. Many students ask for it">
            </div>
            <button type="submit" class="btn btn-primary btn-block">Add suggestion</button>
        </form>

        <div class="suggestion-list">
            <?php if (empty($suggestions)): ?>
                <p class="sub">You have not suggested a book yet.</p>
            <?php else: ?>
                <?php foreach ($suggestions as $s): ?>
                    <div class="suggestion-item">
                        <div>
                            <strong><?= esc($s['title']) ?></strong>
                            <span class="sub">by <?= esc($s['author']) ?></span>
                        </div>
                        <a class="btn-sm btn-delete"
                           href="<?= esc(csrf_url('index.php?page=visitor&action=unsuggest&id=' . (int)$s['id'])) ?>"
                           onclick="return confirm('Remove this suggestion?');">Remove</a>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
<script>
/* ---------- AJAX: search my visit passes ---------- */
liveSearch('passSearch', function () {
    ajaxTable({
        url:     'index.php?page=ajax&action=search_passes&q=' +
                 encodeURIComponent(document.getElementById('passSearch').value.trim()),
        tbody:   'passTable',
        counter: 'passCount',
        columns: 7,
        word:    'passes',
        row: function (p, i) {
            var pill = (p.status === 'booked') ? 'pill-active' : 'pill-rejected';
            return '<tr>' +
                '<td>' + (i + 1) + '</td>' +
                '<td class="nowrap"><strong>' + esc(p.pass_code) + '</strong></td>' +
                '<td class="nowrap">' + esc(p.visit_date) + '</td>' +
                '<td>' + esc(p.purpose) + '</td>' +
                '<td>' + esc(p.guests) + '</td>' +
                '<td><span class="pill ' + pill + '">' + esc(p.status) + '</span></td>' +
                '<td class="text-right">' +
                    '<a class="btn-sm btn-edit" href="index.php?page=visitor&action=edit&id=' + p.id + '">Edit</a>' +
                    '<a class="btn-sm btn-delete" href="index.php?page=visitor&action=delete&id=' + p.id +
                    '&csrf_token=<?= csrf_token() ?>" onclick="return confirm(\'Delete this pass?\');">Delete</a>' +
                '</td>' +
            '</tr>';
        }
    });
});
</script>
</body>
</html>
