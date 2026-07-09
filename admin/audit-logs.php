<?php
/**
 * Admin — Audit Logs / Activity Logs
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin();
$pdo = db();

$adminUser = getCurrentAdmin();
if ($adminUser && ($adminUser['role'] ?? 'mentor') === 'mentor') {
    setFlash('error', 'Access denied. Super Admin privileges required.');
    redirect(SITE_URL . '/admin/dashboard.php');
}

// Filters
$userType  = clean($_GET['user_type'] ?? '');
$actionF    = clean($_GET['action_type'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));

$where = ['1=1'];
$params = [];
if ($userType)  { $where[] = 'al.user_type=?'; $params[] = $userType; }
if ($actionF)   { $where[] = 'al.action=?'; $params[] = $actionF; }
$whereStr = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs al WHERE $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pag = paginate($total, $page);

// Fetch logs
$logs = $pdo->prepare("
    SELECT al.*, 
           CASE al.user_type 
                WHEN 'admin' THEN adm.name 
                ELSE s.name 
           END as user_name,
           CASE al.user_type 
                WHEN 'admin' THEN 'Admin' 
                ELSE s.student_id 
           END as identity_label
    FROM activity_logs al
    LEFT JOIN admin adm ON al.user_type = 'admin' AND al.user_id = adm.id
    LEFT JOIN students s ON al.user_type = 'student' AND al.user_id = s.id
    WHERE $whereStr
    ORDER BY al.created_at DESC
    LIMIT {$pag['per_page']} OFFSET {$pag['offset']}
");
$logs->execute($params);
$logs = $logs->fetchAll();

// Unique actions for filter
$actions = $pdo->query("SELECT DISTINCT action FROM activity_logs ORDER BY action")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle  = 'Audit Logs';
$userRole   = 'admin';
$breadcrumb = [['label'=>'Audit Logs']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-admin.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">Audit Logs</h1>
            <p class="page-subtitle">Detailed tracking of user activities and administrative changes</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:20px">
        <div class="card-body" style="padding:16px 22px">
            <form method="GET" class="d-flex gap-12 align-center flex-wrap">
                <select name="user_type" class="form-control" style="width:180px">
                    <option value="">All Roles</option>
                    <option value="admin" <?= $userType==='admin'?'selected':'' ?>>Admin</option>
                    <option value="student" <?= $userType==='student'?'selected':'' ?>>Student</option>
                </select>
                <select name="action_type" class="form-control" style="width:200px">
                    <option value="">All Actions</option>
                    <?php foreach ($actions as $act): ?>
                    <option value="<?= htmlspecialchars($act) ?>" <?= $actionF===$act?'selected':'' ?>><?= htmlspecialchars($act) ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Filter</button>
                <a href="<?= SITE_URL ?>/admin/audit-logs.php" class="btn btn-secondary btn-sm">Reset</a>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card table-card">
        <div class="card-header">
            <span class="card-title">Activity Trace Log</span>
            <span class="text-sm text-muted"><?= $total ?> total log entries</span>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>User Name</th>
                        <th>Role / ID</th>
                        <th>Action</th>
                        <th>Description</th>
                        <th>IP Address</th>
                        <th>User Agent</th>
                        <th>Timestamp</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($logs)): ?>
                    <tr><td colspan="7">
                        <div class="empty-state">
                            <div class="empty-icon">🕒</div>
                            <div class="empty-title">No audit logs found</div>
                            <div class="empty-msg">Logs are generated automatically when users perform system actions.</div>
                        </div>
                    </td></tr>
                    <?php else: foreach ($logs as $log): ?>
                    <tr>
                        <td class="fw-600 text-sm"><?= htmlspecialchars($log['user_name'] ?? 'System / Unknown') ?></td>
                        <td><?= statusBadge($log['user_type'] === 'admin' ? 'Verified' : 'Present') ?> <span class="text-xs text-muted"><?= htmlspecialchars($log['identity_label']) ?></span></td>
                        <td><code style="font-size:.78rem;background:var(--bg-table-head);padding:2px 6px;border-radius:4px"><?= htmlspecialchars($log['action']) ?></code></td>
                        <td class="text-sm text-secondary" style="max-width:320px; white-space:normal"><?= htmlspecialchars($log['description']) ?></td>
                        <td class="text-xs text-muted"><?= htmlspecialchars($log['ip_address']) ?></td>
                        <td class="text-xs text-muted" style="max-width:180px; overflow:hidden; text-overflow:ellipsis" title="<?= htmlspecialchars($log['user_agent']) ?>"><?= htmlspecialchars($log['user_agent']) ?></td>
                        <td class="text-xs text-muted"><?= formatDateTime($log['created_at']) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <?= renderPagination($pag, SITE_URL . '/admin/audit-logs.php?' . http_build_query(['user_type'=>$userType,'action_type'=>$actionF])) ?>
        </div>
    </div>

</main>
</div>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
