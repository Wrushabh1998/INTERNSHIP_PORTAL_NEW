<?php
/**
 * Admin — Leave Management
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin();
$pdo = db();

// Handle approve/reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        setFlash('error', 'Invalid token.'); redirect(SITE_URL . '/admin/leave.php');
    }
    $id     = (int)($_POST['leave_id'] ?? 0);
    $action = $_POST['action'];
    $remark = clean($_POST['remark'] ?? '');

    if (in_array($action, ['Approved','Rejected']) && $id) {
        $pdo->prepare("UPDATE leave_requests SET status=?, admin_remark=?, reviewed_by=?, reviewed_at=NOW() WHERE id=?")
            ->execute([$action, $remark, $_SESSION['admin_id'], $id]);

        // Get leave info for notification
        $leave = $pdo->prepare("SELECT * FROM leave_requests WHERE id=?");
        $leave->execute([$id]);
        $leave = $leave->fetch();

        if ($leave) {
            createNotification($leave['student_id'], 'Leave',
                "Leave $action",
                "Your leave request from " . formatDate($leave['from_date']) . " to " . formatDate($leave['to_date']) . " has been $action." . ($remark ? " Remark: $remark" : ''),
                'student/leave.php'
            );
            // If approved, mark attendance as Leave
            if ($action === 'Approved') {
                $from = strtotime($leave['from_date']);
                $to   = strtotime($leave['to_date']);
                for ($d = $from; $d <= $to; $d += 86400) {
                    $dt = date('Y-m-d', $d);
                    // Skip if attendance already exists
                    $exists = $pdo->prepare("SELECT id FROM attendance WHERE student_id=? AND date=?");
                    $exists->execute([$leave['student_id'], $dt]);
                    if (!$exists->fetchColumn()) {
                        $pdo->prepare("INSERT INTO attendance (student_id,date,status) VALUES (?,?,'Leave')")
                            ->execute([$leave['student_id'], $dt]);
                    } else {
                        $pdo->prepare("UPDATE attendance SET status='Leave' WHERE student_id=? AND date=?")
                            ->execute([$leave['student_id'], $dt]);
                    }
                }
            }
        }
        logActivity('admin', $_SESSION['admin_id'], 'leave_'.$action, "Leave ID $id $action");
        setFlash('success', "Leave $action successfully.");
    }
    redirect(SITE_URL . '/admin/leave.php');
}

// Filters
$statusF  = clean($_GET['status'] ?? '');
$typeF    = clean($_GET['type']   ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));

$adminUser = getCurrentAdmin();
$isMentor  = $adminUser && ($adminUser['role'] ?? 'mentor') === 'mentor';
$mentorId  = $_SESSION['admin_id'];

$where  = ['1=1'];
$params = [];
if ($statusF) { $where[] = 'lr.status=?'; $params[] = $statusF; }
if ($typeF)   { $where[] = 'lr.leave_type=?'; $params[] = $typeF; }

if ($isMentor) {
    $where[] = 'lr.student_id IN (SELECT id FROM students WHERE mentor_id = ?)';
    $params[] = $mentorId;
}

$whereStr = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests lr WHERE $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pag   = paginate($total, $page);

$leaves = $pdo->prepare("
    SELECT lr.*, s.name student_name, s.student_id sid, s.profile_photo
    FROM leave_requests lr
    JOIN students s ON lr.student_id=s.id
    WHERE $whereStr
    ORDER BY lr.created_at DESC
    LIMIT {$pag['per_page']} OFFSET {$pag['offset']}
");
$leaves->execute($params);
$leaves = $leaves->fetchAll();

$pageTitle  = 'Leave Management';
$userRole   = 'admin';
$breadcrumb = [['label'=>'Leave Management']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-admin.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <h1 class="page-title">Leave Management</h1>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:20px">
        <div class="card-body" style="padding:16px 22px">
            <form method="GET" class="d-flex gap-12 flex-wrap align-center">
                <select name="status" class="form-control" style="width:140px">
                    <option value="">All Status</option>
                    <?php foreach (['Pending','Approved','Rejected'] as $s): ?>
                    <option <?= $statusF===$s?'selected':'' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="type" class="form-control" style="width:140px">
                    <option value="">All Types</option>
                    <?php foreach (['Casual','Sick','Emergency','Medical','Other'] as $t): ?>
                    <option <?= $typeF===$t?'selected':'' ?>><?= $t ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Filter</button>
                <a href="<?= SITE_URL ?>/admin/leave.php" class="btn btn-secondary btn-sm">Reset</a>
            </form>
        </div>
    </div>

    <div class="card table-card">
        <div class="card-header">
            <span class="card-title">Leave Requests</span>
            <span class="text-sm text-muted"><?= $total ?> request(s)</span>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>#</th><th>Student</th><th>Type</th><th>Dates</th><th>Days</th><th>Reason</th><th>Proof</th><th>Status</th><th>Action</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($leaves)): ?>
                    <tr><td colspan="9"><div class="empty-state"><div class="empty-icon">📋</div><div class="empty-title">No leave requests</div></div></td></tr>
                    <?php else: foreach ($leaves as $i => $l): ?>
                    <tr>
                        <td class="text-sm text-muted"><?= $pag['offset']+$i+1 ?></td>
                        <td>
                            <div class="d-flex align-center gap-8">
                                <img src="<?= profilePhotoUrl($l['profile_photo'],'student') ?>" class="avatar avatar-sm" alt="">
                                <div>
                                    <div class="fw-600 text-sm"><?= htmlspecialchars($l['student_name']) ?></div>
                                    <div class="text-xs text-muted"><?= htmlspecialchars($l['sid']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td><span class="badge badge-info"><?= htmlspecialchars($l['leave_type']) ?></span></td>
                        <td class="text-sm"><?= formatDate($l['from_date'],'d M Y') ?> – <?= formatDate($l['to_date'],'d M Y') ?></td>
                        <td class="fw-600"><?= $l['days'] ?> day<?= $l['days']>1?'s':'' ?></td>
                        <td class="text-sm" style="max-width:180px"><?= htmlspecialchars(substr($l['reason'],0,80)) ?>…</td>
                        <td>
                            <?php if ($l['proof_path']): ?>
                            <a href="<?= SITE_URL ?>/uploads/leaves/<?= htmlspecialchars($l['proof_path']) ?>" target="_blank" class="btn btn-ghost btn-sm">
                                <i class="fa fa-paperclip"></i> View
                            </a>
                            <?php else: ?><span class="text-muted text-xs">None</span><?php endif; ?>
                        </td>
                        <td><?= statusBadge($l['status']) ?></td>
                        <td>
                            <?php if ($l['status'] === 'Pending'): ?>
                            <button class="btn btn-success btn-sm"
                                    onclick="openLeaveModal(<?= $l['id'] ?>,'Approved')">
                                <i class="fa fa-check"></i> Approve
                            </button>
                            <button class="btn btn-danger btn-sm"
                                    onclick="openLeaveModal(<?= $l['id'] ?>,'Rejected')">
                                <i class="fa fa-times"></i> Reject
                            </button>
                            <?php else: ?>
                            <span class="text-xs text-muted"><?= $l['admin_remark'] ? htmlspecialchars(substr($l['admin_remark'],0,40)) : 'Reviewed' ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <?= renderPagination($pag, SITE_URL . '/admin/leave.php?' . http_build_query(['status'=>$statusF,'type'=>$typeF])) ?>
        </div>
    </div>

</main>
</div>
</div>

<!-- Leave Action Modal -->
<div class="modal-overlay" id="leave-modal">
    <div class="modal">
        <div class="modal-header">
            <span class="modal-title" id="leave-modal-title">Review Leave</span>
            <button class="modal-close" onclick="Modal.close('leave-modal')">✕</button>
        </div>
        <form method="POST" id="leave-form">
            <?= csrfField() ?>
            <input type="hidden" name="leave_id" id="modal-leave-id">
            <input type="hidden" name="action" id="modal-action">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Admin Remark (optional)</label>
                    <textarea name="remark" class="form-control" rows="3" placeholder="Add your remarks…"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="Modal.close('leave-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="leave-action-btn">Confirm</button>
            </div>
        </form>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
<script>
function openLeaveModal(id, action) {
    document.getElementById('modal-leave-id').value = id;
    document.getElementById('modal-action').value   = action;
    document.getElementById('leave-modal-title').textContent = action === 'Approved' ? '✅ Approve Leave' : '❌ Reject Leave';
    document.getElementById('leave-action-btn').className = action === 'Approved' ? 'btn btn-success' : 'btn btn-danger';
    document.getElementById('leave-action-btn').textContent = action;
    Modal.open('leave-modal');
}
</script>
