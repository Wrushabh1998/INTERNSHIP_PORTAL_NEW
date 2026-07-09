<?php
/**
 * Student — Apply Leave
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/helpers/upload.php';

requireStudent();
$pdo  = db();
$sId  = $_SESSION['student_id'];
$today= date('Y-m-d');

// Handle POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        setFlash('error', 'Invalid token.'); redirect(SITE_URL . '/student/leave.php');
    }

    $type    = in_array($_POST['leave_type']??'',['Casual','Sick','Emergency','Medical','Other']) ? $_POST['leave_type'] : 'Casual';
    $from    = $_POST['from_date'] ?? '';
    $to      = $_POST['to_date']   ?? '';
    $reason  = clean($_POST['reason'] ?? '');

    if (!$from || !$to || !$reason) {
        setFlash('error', 'All fields are required.'); redirect(SITE_URL . '/student/leave.php');
    }
    if (strtotime($from) > strtotime($to)) {
        setFlash('error', 'End date must be after start date.'); redirect(SITE_URL . '/student/leave.php');
    }

    $days = max(1, (int)((strtotime($to) - strtotime($from)) / 86400) + 1);

    // Proof upload
    $proof = null;
    if (!empty($_FILES['proof']['name'])) {
        $up = uploadFile($_FILES['proof'], UPLOAD_LEAVES, array_merge(ALLOWED_IMAGE_TYPES, ['application/pdf']), MAX_FILE_SIZE, 'leave');
        if (!$up['success']) {
            setFlash('error', 'File upload error: ' . $up['error']); redirect(SITE_URL . '/student/leave.php');
        }
        $proof = $up['filename'];
    }

    $pdo->prepare("INSERT INTO leave_requests (student_id,leave_type,from_date,to_date,days,reason,proof_path) VALUES (?,?,?,?,?,?,?)")
        ->execute([$sId, $type, $from, $to, $days, $reason, $proof]);

    // Notify all admins
    $admins = $pdo->query("SELECT id FROM admin")->fetchAll(PDO::FETCH_COLUMN);
    logActivity('student', $sId, 'apply_leave', "$type leave from $from to $to");

    setFlash('success', 'Leave application submitted successfully! Admin will review it shortly.');
    redirect(SITE_URL . '/student/leave.php');
}

// List my leaves
$myLeaves = $pdo->prepare("SELECT * FROM leave_requests WHERE student_id=? ORDER BY created_at DESC");
$myLeaves->execute([$sId]);
$myLeaves = $myLeaves->fetchAll();

// Leave summary
$leaveSum = $pdo->prepare("SELECT leave_type, status, COUNT(*) c FROM leave_requests WHERE student_id=? GROUP BY leave_type,status");
$leaveSum->execute([$sId]);
$totalApproved = (int)$pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE student_id=? AND status='Approved'")->execute([$sId]);
$appStmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE student_id=? AND status='Approved'");
$appStmt->execute([$sId]);
$totalApproved = (int)$appStmt->fetchColumn();

$pageTitle  = 'Leave Management';
$userRole   = 'student';
$breadcrumb = [['label'=>'Leave']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-student.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <h1 class="page-title">Leave Management</h1>
        <button class="btn btn-primary" data-modal-open="leave-modal">
            <i class="fa fa-plus"></i> Apply Leave
        </button>
    </div>

    <!-- Summary -->
    <div class="stat-grid" style="margin-bottom:20px">
        <div class="stat-card success">
            <div class="stat-icon"><i class="fa fa-check-circle"></i></div>
            <div class="stat-body"><div class="stat-value"><?= $totalApproved ?></div><div class="stat-label">Approved Leaves</div></div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon"><i class="fa fa-clock"></i></div>
            <div class="stat-body">
                <?php
                $pendStmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE student_id=? AND status='Pending'");
                $pendStmt->execute([$sId]);
                ?>
                <div class="stat-value"><?= $pendStmt->fetchColumn() ?></div>
                <div class="stat-label">Pending</div>
            </div>
        </div>
        <div class="stat-card danger">
            <div class="stat-icon"><i class="fa fa-times-circle"></i></div>
            <div class="stat-body">
                <?php
                $rejStmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests WHERE student_id=? AND status='Rejected'");
                $rejStmt->execute([$sId]);
                ?>
                <div class="stat-value"><?= $rejStmt->fetchColumn() ?></div>
                <div class="stat-label">Rejected</div>
            </div>
        </div>
    </div>

    <!-- Leave History -->
    <div class="card table-card">
        <div class="card-header"><span class="card-title">My Leave History</span></div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>#</th><th>Type</th><th>From</th><th>To</th><th>Days</th><th>Reason</th><th>Proof</th><th>Status</th><th>Admin Remark</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($myLeaves)): ?>
                    <tr><td colspan="9"><div class="empty-state"><div class="empty-icon">📋</div><div class="empty-title">No leave applications yet</div></div></td></tr>
                    <?php else: foreach ($myLeaves as $i => $l): ?>
                    <tr>
                        <td class="text-muted text-sm"><?= $i+1 ?></td>
                        <td><span class="badge badge-info"><?= $l['leave_type'] ?></span></td>
                        <td class="text-sm"><?= formatDate($l['from_date']) ?></td>
                        <td class="text-sm"><?= formatDate($l['to_date']) ?></td>
                        <td class="fw-600"><?= $l['days'] ?></td>
                        <td class="text-sm"><?= htmlspecialchars(substr($l['reason'],0,60)) ?>…</td>
                        <td>
                            <?php if ($l['proof_path']): ?>
                            <a href="<?= SITE_URL ?>/uploads/leaves/<?= $l['proof_path'] ?>" target="_blank" class="btn btn-ghost btn-sm">
                                <i class="fa fa-paperclip"></i>
                            </a>
                            <?php else: echo '—'; endif; ?>
                        </td>
                        <td><?= statusBadge($l['status']) ?></td>
                        <td class="text-sm text-muted"><?= htmlspecialchars($l['admin_remark'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>
</div>
</div>

<!-- Apply Leave Modal -->
<div class="modal-overlay" id="leave-modal">
    <div class="modal" style="max-width:560px">
        <div class="modal-header">
            <span class="modal-title">Apply for Leave</span>
            <button class="modal-close">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <div class="modal-body">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Leave Type <span class="required">*</span></label>
                        <select name="leave_type" class="form-control" required>
                            <?php foreach (['Casual','Sick','Emergency','Medical','Other'] as $t): ?>
                            <option><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">From Date <span class="required">*</span></label>
                        <input type="date" name="from_date" class="form-control" required min="<?= $today ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">To Date <span class="required">*</span></label>
                        <input type="date" name="to_date" class="form-control" required min="<?= $today ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Reason <span class="required">*</span></label>
                    <textarea name="reason" class="form-control" rows="3" required placeholder="Describe your reason for leave…"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Proof Document (optional)</label>
                    <input type="file" name="proof" class="form-control" accept=".pdf,.jpg,.jpeg,.png">
                    <div class="form-text">Medical certificate, etc. Max 5MB.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Submit Application</button>
            </div>
        </form>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
