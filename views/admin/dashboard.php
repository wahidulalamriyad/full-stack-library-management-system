<?php
$pageTitle   = 'Admin dashboard';
$pageHeading = 'User accounts';
$pageSub     = 'Create, edit, suspend and remove accounts for every role';
$isEdit      = !empty($editing);
require __DIR__ . '/../partials/header.php';
?>

<!-- ============ FEATURE 3: live statistics (refreshed by AJAX) ============ -->
<section class="stat-grid" id="statGrid">
    <div class="stat-card">
        <span class="stat-value" data-stat="students"><?= (int)$stats['students'] ?></span>
        <span class="stat-label">Students</span>
    </div>
    <div class="stat-card">
        <span class="stat-value" data-stat="librarians"><?= (int)$stats['librarians'] ?></span>
        <span class="stat-label">Librarians</span>
    </div>
    <div class="stat-card">
        <span class="stat-value" data-stat="visitors"><?= (int)$stats['visitors'] ?></span>
        <span class="stat-label">Visitors</span>
    </div>
    <div class="stat-card">
        <span class="stat-value" data-stat="suspended"><?= (int)$stats['suspended'] ?></span>
        <span class="stat-label">Suspended</span>
    </div>
    <div class="stat-card">
        <span class="stat-value" data-stat="books"><?= (int)$stats['books'] ?></span>
        <span class="stat-label">Book titles</span>
    </div>
    <div class="stat-card">
        <span class="stat-value" data-stat="issued"><?= (int)$stats['issued'] ?></span>
        <span class="stat-label">Books out</span>
    </div>
</section>
<p class="stat-note">Counts refresh every 15 seconds without reloading the page.</p>

<!-- ============ CREATE / UPDATE form ============ -->
<div class="card form-card">
    <h3 class="card-title">
        <?= $isEdit ? 'Edit account #' . (int)$editing['id'] : 'Add a new account' ?>
    </h3>

    <form method="POST" class="form" novalidate onsubmit="return validateForm(this);"
          action="index.php?page=admin&amp;action=<?= $isEdit ? 'update&amp;id=' . (int)$editing['id'] : 'add' ?>">
        <?php csrf_field(); ?>

        <div class="field-row">
            <div class="field">
                <label for="name">Full name</label>
                <input type="text" id="name" name="name" data-label="Full name" data-min="3"
                       value="<?= esc($editing['name'] ?? '') ?>" placeholder="Full name" required>
            </div>
            <div class="field">
                <label for="email">Email</label>
                <input type="email" id="email" name="email" data-label="Email"
                       value="<?= esc($editing['email'] ?? '') ?>" placeholder="name@example.com" required>
            </div>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="contact">Contact number</label>
                <input type="text" id="contact" name="contact" data-label="Contact number" data-phone="1"
                       value="<?= esc($editing['contact'] ?? '') ?>" placeholder="+880 1XXXXXXXXX" required>
            </div>
            <div class="field">
                <label for="username">Username</label>
                <input type="text" id="username" name="username" data-label="Username" data-min="4"
                       value="<?= esc($editing['username'] ?? '') ?>" placeholder="Login username" required>
            </div>
        </div>

        <div class="field-row">
            <div class="field">
                <label for="role">Role</label>
                <select id="role" name="role" data-label="Role" required>
                    <?php foreach (['admin', 'librarian', 'student', 'visitor'] as $r): ?>
                        <option value="<?= $r ?>"
                            <?= (($editing['role'] ?? 'student') === $r) ? 'selected' : '' ?>>
                            <?= esc(role_label($r)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <?php if ($isEdit): ?>
                <div class="field">
                    <label for="status">Status</label>
                    <select id="status" name="status" data-label="Status" required>
                        <option value="active"    <?= (($editing['status'] ?? '') === 'active')    ? 'selected' : '' ?>>Active</option>
                        <option value="suspended" <?= (($editing['status'] ?? '') === 'suspended') ? 'selected' : '' ?>>Suspended</option>
                    </select>
                </div>
            <?php else: ?>
                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" data-label="Password" data-min="6"
                           placeholder="At least 6 characters" required>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($isEdit): ?>
            <div class="field">
                <label for="password">New password <span class="label-hint">(leave blank to keep the current one)</span></label>
                <input type="password" id="password" name="password" data-label="New password" data-min="6"
                       placeholder="Only fill this in to change the password">
            </div>
        <?php endif; ?>

        <div class="form-actions">
            <?php if ($isEdit): ?>
                <a href="index.php?page=admin" class="btn btn-ghost">Cancel</a>
                <button type="submit" class="btn btn-primary">Save changes</button>
            <?php else: ?>
                <button type="submit" class="btn btn-primary">Create account</button>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- ============ READ + SEARCH: all accounts ============ -->
<div class="card">
    <div class="card-toolbar">
        <div class="search-wrap">
            <span class="search-icon">&#128269;</span>
            <input type="text" id="userSearch" class="search-input"
                   placeholder="Search by name, username, email or phone...">
        </div>
        <select id="roleFilter" class="filter-select">
            <option value="">All roles</option>
            <option value="admin"     <?= $roleFilter === 'admin'     ? 'selected' : '' ?>>Administrators</option>
            <option value="librarian" <?= $roleFilter === 'librarian' ? 'selected' : '' ?>>Librarians</option>
            <option value="student"   <?= $roleFilter === 'student'   ? 'selected' : '' ?>>Students</option>
            <option value="visitor"   <?= $roleFilter === 'visitor'   ? 'selected' : '' ?>>Visitors</option>
        </select>
        <span class="badge" id="userCount"><?= count($users) ?> accounts</span>
    </div>

    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr>
                    <th>#</th>
                    <th>Name</th>
                    <th>Username</th>
                    <th>Email</th>
                    <th>Contact</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody id="userTable">
                <?php if (empty($users)): ?>
                    <tr><td colspan="8" class="empty">No accounts match this filter.</td></tr>
                <?php else: ?>
                    <?php foreach ($users as $i => $u): ?>
                        <tr>
                            <td><?= $i + 1 ?></td>
                            <td><?= esc($u['name']) ?></td>
                            <td><?= esc($u['username']) ?></td>
                            <td><?= esc($u['email']) ?></td>
                            <td><?= esc($u['contact']) ?></td>
                            <td><span class="pill pill-<?= esc($u['role']) ?>"><?= esc(role_label($u['role'])) ?></span></td>
                            <td><span class="pill pill-<?= esc($u['status']) ?>"><?= esc(ucfirst($u['status'])) ?></span></td>
                            <td class="text-right">
                                <a class="btn-sm btn-edit"
                                   href="index.php?page=admin&amp;action=edit&amp;id=<?= (int)$u['id'] ?>">Edit</a>
                                <?php if ($u['status'] === 'active'): ?>
                                    <a class="btn-sm btn-warn"
                                       href="<?= esc(csrf_url('index.php?page=admin&action=status&to=suspended&id=' . (int)$u['id'])) ?>"
                                       onclick="return confirm('Suspend this account?');">Suspend</a>
                                <?php else: ?>
                                    <a class="btn-sm btn-ok"
                                       href="<?= esc(csrf_url('index.php?page=admin&action=status&to=active&id=' . (int)$u['id'])) ?>">Activate</a>
                                <?php endif; ?>
                                <a class="btn-sm btn-delete"
                                   href="<?= esc(csrf_url('index.php?page=admin&action=delete&id=' . (int)$u['id'])) ?>"
                                   onclick="return confirm('Delete this account for good?');">Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============ FEATURE 1b: membership upgrade requests ============ -->
<div class="card">
    <h3 class="card-title card-title-pad">Membership upgrade requests</h3>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>Visitor</th><th>Username</th><th>Why they want to join</th><th class="text-right">Decision</th></tr>
            </thead>
            <tbody>
                <?php if (empty($applications)): ?>
                    <tr><td colspan="4" class="empty">No visitor is waiting for a decision.</td></tr>
                <?php else: ?>
                    <?php foreach ($applications as $app): ?>
                        <tr>
                            <td><?= esc($app['name']) ?></td>
                            <td><?= esc($app['username']) ?></td>
                            <td><?= esc($app['reason']) ?></td>
                            <td class="text-right">
                                <a class="btn-sm btn-ok"
                                   href="<?= esc(csrf_url('index.php?page=admin&action=membership&to=approved&id=' . (int)$app['id'])) ?>"
                                   onclick="return confirm('Approve and turn this visitor into a student?');">Approve</a>
                                <a class="btn-sm btn-delete"
                                   href="<?= esc(csrf_url('index.php?page=admin&action=membership&to=rejected&id=' . (int)$app['id'])) ?>">Reject</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- ============ FEATURE 2: activity log ============ -->
<div class="card">
    <div class="card-toolbar">
        <div class="search-wrap">
            <span class="search-icon">&#128269;</span>
            <input type="text" id="logSearch" class="search-input"
                   placeholder="Search the activity log by user, role or action...">
        </div>
        <span class="badge" id="logCount"><?= count($logs) ?> entries</span>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead>
                <tr><th>When</th><th>User</th><th>Role</th><th>Action</th><th>IP address</th></tr>
            </thead>
            <tbody id="logTable">
                <?php if (empty($logs)): ?>
                    <tr><td colspan="5" class="empty">Nothing has happened yet.</td></tr>
                <?php else: ?>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td class="nowrap"><?= esc(date('d M, H:i', strtotime($log['created_at']))) ?></td>
                            <td><?= esc($log['username']) ?></td>
                            <td><span class="pill pill-<?= esc($log['role']) ?>"><?= esc(role_label($log['role'])) ?></span></td>
                            <td><?= esc($log['action']) ?></td>
                            <td><?= esc($log['ip']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php require __DIR__ . '/../partials/footer.php'; ?>
<script>
/* ---------- AJAX 1: live account search + role filter ---------- */
var roleFilter = document.getElementById('roleFilter');

function userRow(u, i) {
    var suspendLink = (u.status === 'active')
        ? '<a class="btn-sm btn-warn" href="index.php?page=admin&action=status&to=suspended&id=' + u.id +
          '&csrf_token=<?= csrf_token() ?>" onclick="return confirm(\'Suspend this account?\');">Suspend</a>'
        : '<a class="btn-sm btn-ok" href="index.php?page=admin&action=status&to=active&id=' + u.id +
          '&csrf_token=<?= csrf_token() ?>">Activate</a>';

    return '<tr>' +
        '<td>' + (i + 1) + '</td>' +
        '<td>' + esc(u.name) + '</td>' +
        '<td>' + esc(u.username) + '</td>' +
        '<td>' + esc(u.email) + '</td>' +
        '<td>' + esc(u.contact) + '</td>' +
        '<td><span class="pill pill-' + esc(u.role) + '">' + esc(u.role) + '</span></td>' +
        '<td><span class="pill pill-' + esc(u.status) + '">' + esc(u.status) + '</span></td>' +
        '<td class="text-right">' +
            '<a class="btn-sm btn-edit" href="index.php?page=admin&action=edit&id=' + u.id + '">Edit</a>' +
            suspendLink +
            '<a class="btn-sm btn-delete" href="index.php?page=admin&action=delete&id=' + u.id +
            '&csrf_token=<?= csrf_token() ?>" onclick="return confirm(\'Delete this account for good?\');">Delete</a>' +
        '</td>' +
    '</tr>';
}

function runUserSearch() {
    var term = document.getElementById('userSearch').value.trim();
    ajaxTable({
        url:     'index.php?page=ajax&action=search_users&role=' + encodeURIComponent(roleFilter.value) +
                 '&q=' + encodeURIComponent(term),
        tbody:   'userTable',
        counter: 'userCount',
        columns: 8,
        word:    'accounts',
        row:     userRow
    });
}

liveSearch('userSearch', runUserSearch);
roleFilter.addEventListener('change', runUserSearch);

/* ---------- AJAX 2: live statistics panel ---------- */
function refreshStats() {
    fetch('index.php?page=ajax&action=stats')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            document.querySelectorAll('[data-stat]').forEach(function (el) {
                var key = el.getAttribute('data-stat');
                if (data[key] !== undefined) { el.textContent = data[key]; }
            });
        })
        .catch(function () { /* ignore a failed refresh */ });
}
setInterval(refreshStats, 15000);

/* ---------- AJAX 3: activity log search ---------- */
liveSearch('logSearch', function () {
    ajaxTable({
        url:     'index.php?page=ajax&action=search_logs&q=' +
                 encodeURIComponent(document.getElementById('logSearch').value.trim()),
        tbody:   'logTable',
        counter: 'logCount',
        columns: 5,
        word:    'entries',
        row: function (log) {
            return '<tr>' +
                '<td class="nowrap">' + esc(log.created_at) + '</td>' +
                '<td>' + esc(log.username) + '</td>' +
                '<td><span class="pill pill-' + esc(log.role) + '">' + esc(log.role) + '</span></td>' +
                '<td>' + esc(log.action) + '</td>' +
                '<td>' + esc(log.ip) + '</td>' +
            '</tr>';
        }
    });
});
</script>
</body>
</html>
