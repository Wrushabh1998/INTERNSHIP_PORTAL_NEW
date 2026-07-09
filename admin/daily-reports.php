<?php
/**
 * Admin — Daily Report Verification
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin();
$pdo = db();

// Handle Approve / Reject
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        setFlash('error', 'Invalid security token.');
        redirect(SITE_URL . '/admin/daily-reports.php');
    }
    
    $reportId = (int)($_POST['report_id'] ?? 0);
    $action   = $_POST['action']; // Approved or Rejected
    $remark   = clean($_POST['admin_remark'] ?? '');

    if ($reportId && in_array($action, ['Approved', 'Rejected'])) {
        $stmt = $pdo->prepare("
            UPDATE daily_reports 
            SET status=?, admin_remark=?, reviewed_by=?, reviewed_at=NOW() 
            WHERE id=?
        ");
        $stmt->execute([$action, $remark, $_SESSION['admin_id'], $reportId]);

        // Fetch report student info to notify
        $repStmt = $pdo->prepare("SELECT student_id, date FROM daily_reports WHERE id=?");
        $repStmt->execute([$reportId]);
        $rep = $repStmt->fetch();
        if ($rep) {
            createNotification(
                $rep['student_id'], 
                'General', 
                "Daily Report $action", 
                "Your daily work report for " . formatDate($rep['date']) . " has been $action." . ($remark ? " Remark: $remark" : ""), 
                'student/daily-report.php'
            );
        }

        logActivity('admin', $_SESSION['admin_id'], 'verify_report', "Report ID $reportId $action");
        setFlash('success', "Report status updated to $action.");
    }
    redirect(SITE_URL . '/admin/daily-reports.php');
}

// Filters
$studentId  = (int)($_GET['student'] ?? 0);
$statusF    = clean($_GET['status'] ?? 'Pending');
$page       = max(1, (int)($_GET['page'] ?? 1));

$adminUser = getCurrentAdmin();
$isMentor  = $adminUser && ($adminUser['role'] ?? 'mentor') === 'mentor';
$mentorId  = $_SESSION['admin_id'];

$where = ['1=1'];
$params = [];
if ($studentId) { $where[] = 'dr.student_id=?'; $params[] = $studentId; }
if ($statusF)   { $where[] = 'dr.status=?'; $params[] = $statusF; }

if ($isMentor) {
    $where[] = 'dr.student_id IN (SELECT id FROM students WHERE mentor_id = ?)';
    $params[] = $mentorId;
}

$whereStr = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM daily_reports dr WHERE $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pag = paginate($total, $page);

// Fetch reports
$reports = $pdo->prepare("
    SELECT dr.*, s.name as student_name, s.student_id as sid, s.profile_photo, p.title as project_title
    FROM daily_reports dr
    JOIN students s ON dr.student_id = s.id
    LEFT JOIN projects p ON dr.project_id = p.id
    WHERE $whereStr
    ORDER BY dr.date DESC, dr.created_at DESC
    LIMIT {$pag['per_page']} OFFSET {$pag['offset']}
");
$reports->execute($params);
$reports = $reports->fetchAll();

if ($isMentor) {
    $stmt_studs = $pdo->prepare("SELECT id, name, student_id FROM students WHERE is_active=1 AND mentor_id=? ORDER BY name");
    $stmt_studs->execute([$mentorId]);
} else {
    $stmt_studs = $pdo->prepare("SELECT id, name, student_id FROM students WHERE is_active=1 ORDER BY name");
    $stmt_studs->execute();
}
$students = $stmt_studs->fetchAll();

$pageTitle  = 'Daily Work Reports';
$userRole   = 'admin';
$breadcrumb = [['label'=>'Daily Reports']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-admin.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">Daily Work Reports</h1>
            <p class="page-subtitle">Verify daily submissions and track hours worked</p>
        </div>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:20px">
        <div class="card-body" style="padding:16px 22px">
            <form method="GET" class="d-flex gap-12 align-center flex-wrap">
                <select name="student" class="form-control" style="width:220px">
                    <option value="">All Students</option>
                    <?php foreach ($students as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $studentId==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['student_id']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <select name="status" class="form-control" style="width:160px">
                    <option value="">All Status</option>
                    <?php foreach (['Pending','Approved','Rejected'] as $st): ?>
                    <option value="<?= $st ?>" <?= $statusF===$st?'selected':'' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Filter</button>
                <a href="<?= SITE_URL ?>/admin/daily-reports.php" class="btn btn-secondary btn-sm">Reset</a>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card table-card">
        <div class="card-header">
            <span class="card-title">Submissions List</span>
            <span class="text-sm text-muted"><?= $total ?> report(s) found</span>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Date &amp; Hours</th>
                        <th>Project / Module</th>
                        <th>Work Done</th>
                        <th>Screenshots / Links</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($reports)): ?>
                    <tr><td colspan="7">
                        <div class="empty-state">
                            <div class="empty-icon">📝</div>
                            <div class="empty-title">No reports found</div>
                            <div class="empty-msg">No submissions match your filtering settings.</div>
                        </div>
                    </td></tr>
                    <?php else: foreach ($reports as $r): ?>
                    <tr>
                        <td>
                            <div class="d-flex align-center gap-8">
                                <img src="<?= profilePhotoUrl($r['profile_photo'],'student') ?>" class="avatar avatar-sm" alt="">
                                <div>
                                    <div class="fw-600 text-sm"><?= htmlspecialchars($r['student_name']) ?></div>
                                    <div class="text-xs text-muted"><?= htmlspecialchars($r['sid']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="text-sm">
                            <strong><?= formatDate($r['date']) ?></strong>
                            <div class="text-xs text-muted">Hours Worked: <strong><?= $r['hours_worked'] ?>h</strong></div>
                        </td>
                        <td class="text-sm">
                            <div><?= htmlspecialchars($r['project_title'] ?? 'Independent') ?></div>
                            <div class="text-xs text-muted"><?= htmlspecialchars($r['module'] ?? 'None') ?></div>
                        </td>
                        <td class="text-sm" style="max-width:240px">
                            <div class="fw-600"><?= htmlspecialchars($r['work_done']) ?></div>
                            <?php if ($r['description']): ?>
                            <div class="text-xs text-muted mt-8" style="white-space:normal"><?= htmlspecialchars($r['description']) ?></div>
                            <?php endif; ?>
                            <?php if ($r['challenges']): ?>
                            <div class="text-xs text-danger mt-8" style="white-space:normal">⚠️ <strong>Challenge:</strong> <?= htmlspecialchars($r['challenges']) ?></div>
                            <?php endif; ?>
                            <?php if ($r['solution']): ?>
                            <div class="text-xs text-success mt-8" style="white-space:normal">💡 <strong>Solution:</strong> <?= htmlspecialchars($r['solution']) ?></div>
                            <?php endif; ?>
                        </td>
                        <td class="text-sm">
                            <div class="d-flex gap-8 align-center">
                                <?php if ($r['screenshot']): ?>
                                <a href="<?= SITE_URL ?>/uploads/reports/<?= htmlspecialchars($r['screenshot']) ?>" target="_blank" class="btn btn-ghost btn-icon-sm" title="View Screenshot">
                                    <i class="fa fa-image"></i>
                                </a>
                                <?php endif; ?>
                                <?php if ($r['github_link']): ?>
                                <a href="<?= htmlspecialchars($r['github_link']) ?>" target="_blank" class="btn btn-ghost btn-icon-sm" title="GitHub Code">
                                    <i class="fab fa-github"></i>
                                </a>
                                <?php endif; ?>
                                <?php if ($r['live_url']): ?>
                                <a href="<?= htmlspecialchars($r['live_url']) ?>" target="_blank" class="btn btn-ghost btn-icon-sm" title="Live Preview">
                                    <i class="fa fa-external-link-alt"></i>
                                </a>
                                <?php endif; ?>
                            </div>
                        </td>
                        <td><?= statusBadge($r['status']) ?></td>
                        <td>
                            <?php if ($r['status'] === 'Pending'): ?>
                            <div class="d-flex gap-6">
                                <button class="btn btn-success btn-sm" onclick="openReviewModal(<?= $r['id'] ?>, 'Approved')">Approve</button>
                                <button class="btn btn-danger btn-sm" onclick="openReviewModal(<?= $r['id'] ?>, 'Rejected')">Reject</button>
                            </div>
                            <?php else: ?>
                            <span class="text-xs text-muted"><?= htmlspecialchars($r['admin_remark'] ?? 'Reviewed') ?></span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <?= renderPagination($pag, SITE_URL . '/admin/daily-reports.php?' . http_build_query(['student'=>$studentId,'status'=>$statusF])) ?>
        </div>
    </div>

</main>
</div>
</div>

<!-- Review Modal -->
<div class="modal-overlay" id="review-modal">
    <div class="modal" style="max-width:440px">
        <div class="modal-header">
            <span class="modal-title" id="review-title">Review Daily Report</span>
            <button class="modal-close" onclick="Modal.close('review-modal')">✕</button>
        </div>
        <form method="POST">
            <?= csrfField() ?>
            <input type="hidden" name="report_id" id="modal-report-id">
            <input type="hidden" name="action" id="modal-action">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Feedback / Remarks</label>
                    <textarea name="admin_remark" class="form-control" rows="3" placeholder="Add optional review feedback…"></textarea>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="Modal.close('review-modal')">Cancel</button>
                <button type="submit" class="btn btn-primary" id="modal-submit-btn">Submit Review</button>
            </div>
        </form>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
<script>
function openReviewModal(id, action) {
    document.getElementById('modal-report-id').value = id;
    document.getElementById('modal-action').value    = action;
    document.getElementById('review-title').textContent = action === 'Approved' ? '✅ Approve Report' : '❌ Reject Report';
    document.getElementById('modal-submit-btn').className = action === 'Approved' ? 'btn btn-success' : 'btn btn-danger';
    document.getElementById('modal-submit-btn').textContent = action;
    Modal.open('review-modal');
}
</script>
