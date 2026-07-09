<?php
/**
 * Student — Daily Work Report
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

// Handle POST — submit report
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        setFlash('error', 'Invalid token.'); redirect(SITE_URL . '/student/daily-report.php');
    }
    $date      = $_POST['date']       ?? $today;
    $projectId = (int)($_POST['project_id'] ?? 0) ?: null;
    $module    = clean($_POST['module']     ?? '');
    $workDone  = clean($_POST['work_done']  ?? '');
    $desc      = clean($_POST['description'] ?? '');
    $hours     = (float)($_POST['hours_worked'] ?? 0);
    $challenges= clean($_POST['challenges'] ?? '');
    $solution  = clean($_POST['solution']   ?? '');
    $github    = clean($_POST['github_link'] ?? '');
    $liveUrl   = clean($_POST['live_url']   ?? '');
    $remarks   = clean($_POST['remarks']    ?? '');

    if (!$workDone) { setFlash('error', 'Work done field is required.'); redirect(SITE_URL . '/student/daily-report.php'); }

    // Check duplicate
    $dup = $pdo->prepare("SELECT id FROM daily_reports WHERE student_id=? AND date=?");
    $dup->execute([$sId, $date]);
    if ($dup->fetchColumn()) {
        setFlash('error', 'Report for this date already submitted. Edit it instead.');
        redirect(SITE_URL . '/student/daily-report.php');
    }

    // Screenshot upload
    $screenshot = null;
    if (!empty($_FILES['screenshot']['name'])) {
        $up = uploadFile($_FILES['screenshot'], UPLOAD_REPORTS, ALLOWED_IMAGE_TYPES, MAX_FILE_SIZE, 'report');
        if ($up['success']) $screenshot = $up['filename'];
    }

    $pdo->prepare("INSERT INTO daily_reports (student_id,project_id,date,module,work_done,description,hours_worked,challenges,solution,github_link,live_url,screenshot,remarks)
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)")
        ->execute([$sId, $projectId, $date, $module, $workDone, $desc, $hours, $challenges, $solution, $github, $liveUrl, $screenshot, $remarks]);

    logActivity('student', $sId, 'submit_report', "Report submitted for $date");
    setFlash('success', 'Daily report submitted successfully!');
    redirect(SITE_URL . '/student/daily-report.php');
}

// My projects for dropdown
$projects = $pdo->prepare("SELECT p.* FROM projects p JOIN project_assignments pa ON p.id=pa.project_id WHERE pa.student_id=?");
$projects->execute([$sId]);
$projects = $projects->fetchAll();

// Recent reports
$reports = $pdo->prepare("
    SELECT dr.*, p.title project_name
    FROM daily_reports dr LEFT JOIN projects p ON dr.project_id=p.id
    WHERE dr.student_id=? ORDER BY dr.date DESC LIMIT 15
");
$reports->execute([$sId]);
$reports = $reports->fetchAll();

// Check if today's report exists
$todayReport = $pdo->prepare("SELECT id FROM daily_reports WHERE student_id=? AND date=?");
$todayReport->execute([$sId, $today]);
$todayReport = $todayReport->fetchColumn();

$pageTitle  = 'Daily Work Report';
$userRole   = 'student';
$breadcrumb = [['label'=>'Daily Report']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-student.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <h1 class="page-title">Daily Work Report</h1>
        <?php if (!$todayReport): ?>
        <button class="btn btn-primary" data-modal-open="report-modal">
            <i class="fa fa-plus"></i> Submit Today's Report
        </button>
        <?php else: ?>
        <span class="badge badge-success" style="padding:8px 16px;font-size:.85rem">✅ Today's Report Submitted</span>
        <?php endif; ?>
    </div>

    <!-- Recent Reports -->
    <div class="card table-card">
        <div class="card-header"><span class="card-title">My Reports</span></div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>Date</th><th>Project</th><th>Module</th><th>Work Done</th><th>Hours</th><th>GitHub</th><th>Status</th><th>Admin Remark</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($reports)): ?>
                    <tr><td colspan="8"><div class="empty-state"><div class="empty-icon">📝</div><div class="empty-title">No reports submitted yet</div></div></td></tr>
                    <?php else: foreach ($reports as $r): ?>
                    <tr>
                        <td class="fw-600 text-sm"><?= formatDate($r['date'],'d M Y') ?></td>
                        <td class="text-sm"><?= htmlspecialchars($r['project_name'] ?? '—') ?></td>
                        <td class="text-sm"><?= htmlspecialchars($r['module'] ?? '—') ?></td>
                        <td class="text-sm"><?= htmlspecialchars(substr($r['work_done'],0,70)) ?>…</td>
                        <td class="fw-600"><?= $r['hours_worked'] ?>h</td>
                        <td>
                            <?php if ($r['github_link']): ?>
                            <a href="<?= htmlspecialchars($r['github_link']) ?>" target="_blank" class="btn btn-ghost btn-sm">
                                <i class="fa fa-code-branch"></i>
                            </a>
                            <?php else: echo '—'; endif; ?>
                        </td>
                        <td><?= statusBadge($r['status']) ?></td>
                        <td class="text-sm text-muted"><?= htmlspecialchars($r['admin_remark'] ?? '—') ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>
</div>
</div>

<!-- Submit Report Modal -->
<div class="modal-overlay" id="report-modal">
    <div class="modal" style="max-width:680px">
        <div class="modal-header">
            <span class="modal-title">📝 Submit Daily Work Report</span>
            <button class="modal-close">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <div class="modal-body" style="max-height:65vh;overflow-y:auto">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Date <span class="required">*</span></label>
                        <input type="date" name="date" class="form-control" value="<?= $today ?>" max="<?= $today ?>" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Project</label>
                        <select name="project_id" class="form-control">
                            <option value="">— Select Project —</option>
                            <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= htmlspecialchars($p['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Module / Feature</label>
                        <input type="text" name="module" class="form-control" placeholder="e.g. Login Module">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Hours Worked</label>
                        <input type="number" name="hours_worked" class="form-control" min="0" max="16" step="0.5" value="8">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Work Done Today <span class="required">*</span></label>
                    <textarea name="work_done" class="form-control" rows="3" required placeholder="Briefly describe what you accomplished today…"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Detailed Description</label>
                    <textarea name="description" class="form-control" rows="3" placeholder="More details about your work…"></textarea>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Challenges Faced</label>
                        <textarea name="challenges" class="form-control" rows="2" placeholder="What issues did you face?"></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Solution / Approach</label>
                        <textarea name="solution" class="form-control" rows="2" placeholder="How did you solve them?"></textarea>
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">GitHub Link</label>
                        <input type="url" name="github_link" class="form-control" placeholder="https://github.com/…">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Live URL</label>
                        <input type="url" name="live_url" class="form-control" placeholder="https://…">
                    </div>
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Screenshot</label>
                        <input type="file" name="screenshot" class="form-control" accept=".jpg,.jpeg,.png,.webp">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="2" placeholder="Additional remarks…"></textarea>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-paper-plane"></i> Submit Report</button>
            </div>
        </form>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
