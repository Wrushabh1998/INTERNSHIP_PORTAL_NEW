<?php
/**
 * Admin/Mentor — Checkbox Manual Attendance Sheet
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin();
$pdo = db();

$adminUser = getCurrentAdmin();
$isMentor  = $adminUser && ($adminUser['role'] ?? 'mentor') === 'mentor';
$mentorId  = $_SESSION['admin_id'];

// Get Date
$dateFilter = clean($_GET['date'] ?? date('Y-m-d'));

// Get all active students under this mentor / all students if superadmin
if ($isMentor) {
    $stmt = $pdo->prepare("SELECT s.id, s.name, s.student_id, d.name as dept_name FROM students s LEFT JOIN departments d ON s.department_id=d.id WHERE s.is_active=1 AND s.mentor_id=? ORDER BY s.name");
    $stmt->execute([$mentorId]);
} else {
    $stmt = $pdo->prepare("SELECT s.id, s.name, s.student_id, d.name as dept_name FROM students s LEFT JOIN departments d ON s.department_id=d.id WHERE s.is_active=1 ORDER BY s.name");
    $stmt->execute();
}
$students = $stmt->fetchAll();

// Fetch existing attendance for this date
$stmtAtt = $pdo->prepare("SELECT student_id, status FROM attendance WHERE date=?");
$stmtAtt->execute([$dateFilter]);
$existingAtt = array_column($stmtAtt->fetchAll(), 'status', 'student_id');

// Process POST Form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        setFlash('error', 'Invalid security token.');
    } else {
        $presentStudentIds = $_POST['present_students'] ?? []; // Array of checked student IDs
        
        $successCount = 0;
        
        foreach ($students as $student) {
            $sid = $student['id'];
            $status = in_array($sid, $presentStudentIds) ? 'Present' : 'Absent';
            
            // Check if attendance already exists
            $chk = $pdo->prepare("SELECT id FROM attendance WHERE student_id=? AND date=?");
            $chk->execute([$sid, $dateFilter]);
            $attId = $chk->fetchColumn();
            
            if ($status === 'Present') {
                if ($attId) {
                    // Update
                    $upd = $pdo->prepare("UPDATE attendance SET status='Present', punch_in='09:00:00', punch_out='18:00:00', working_hours=8.0 WHERE id=?");
                    $upd->execute([$attId]);
                } else {
                    // Insert
                    $ins = $pdo->prepare("INSERT INTO attendance (student_id, date, status, punch_in, punch_out, working_hours) VALUES (?, ?, 'Present', '09:00:00', '18:00:00', 8.0)");
                    $ins->execute([$sid, $dateFilter]);
                }
            } else {
                // Absent
                if ($attId) {
                    // Update
                    $upd = $pdo->prepare("UPDATE attendance SET status='Absent', punch_in=NULL, punch_out=NULL, working_hours=0.0, lunch_start=NULL, lunch_end=NULL WHERE id=?");
                    $upd->execute([$attId]);
                } else {
                    // Insert
                    $ins = $pdo->prepare("INSERT INTO attendance (student_id, date, status, punch_in, punch_out, working_hours) VALUES (?, ?, 'Absent', NULL, NULL, 0.0)");
                    $ins->execute([$sid, $dateFilter]);
                }
            }
            $successCount++;
        }
        
        logActivity('admin', $_SESSION['admin_id'], 'manual_attendance', "Marked manual attendance for date: $dateFilter. Count: $successCount");
        setFlash('success', "Attendance saved successfully for $successCount student(s)!");
        redirect(SITE_URL . '/admin/attendance.php?date=' . $dateFilter);
    }
}

$pageTitle  = 'Manual Checkbox Attendance';
$userRole   = 'admin';
$breadcrumb = [['label'=>'Attendance','url'=>SITE_URL.'/admin/attendance.php'],['label'=>'Manual Checkbox']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-admin.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">Manual Checkbox Attendance</h1>
            <p class="page-subtitle">Select date and check the students who are present</p>
        </div>
        <a href="<?= SITE_URL ?>/admin/attendance.php?date=<?= $dateFilter ?>" class="btn btn-secondary btn-sm">
            <i class="fa fa-arrow-left"></i> Back to Logs
        </a>
    </div>

    <!-- Date selector -->
    <div class="card" style="margin-bottom: 20px">
        <div class="card-body" style="padding:16px 22px">
            <form method="GET" class="d-flex gap-12 align-center">
                <div class="form-group" style="margin:0">
                    <label class="form-label" style="margin-right:8px; display:inline-block">Attendance Date:</label>
                    <input type="date" name="date" class="form-control" value="<?= $dateFilter ?>" onchange="this.form.submit()" style="display:inline-block; width:200px">
                </div>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-sync"></i> Load Sheet</button>
            </form>
        </div>
    </div>

    <!-- Table form -->
    <form method="POST">
        <?= csrfField() ?>
        <div class="card table-card" style="margin-bottom: 20px">
            <div class="card-header d-flex justify-between align-center">
                <span class="card-title">Student Attendance Sheet — <?= formatDate($dateFilter, 'd M Y') ?></span>
                <div>
                    <button type="button" class="btn btn-ghost btn-sm" id="btn-select-all" style="padding: 4px 8px; font-size: 0.8rem">Select All</button>
                    <button type="button" class="btn btn-ghost btn-sm" id="btn-deselect-all" style="padding: 4px 8px; font-size: 0.8rem">Deselect All</button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th style="width: 50px">#</th>
                            <th style="width: 80px; text-align:center">Present?</th>
                            <th>Student</th>
                            <th>Student ID</th>
                            <th>Department</th>
                            <th>Current Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($students)): ?>
                        <tr>
                            <td colspan="6">
                                <div class="empty-state">
                                    <div class="empty-icon">👥</div>
                                    <div class="empty-title">No students found</div>
                                    <div class="empty-msg">You have no active students assigned to you.</div>
                                </div>
                            </td>
                        </tr>
                        <?php else: foreach ($students as $idx => $s): 
                            $status = $existingAtt[$s['id']] ?? 'Absent';
                            $isPresent = in_array($status, ['Present', 'Late', 'Half Day']);
                        ?>
                        <tr>
                            <td class="text-sm text-muted"><?= $idx + 1 ?></td>
                            <td style="text-align: center">
                                <label class="checkbox-container" style="display: inline-block; cursor: pointer; position: relative; padding-left: 24px;">
                                    <input type="checkbox" name="present_students[]" value="<?= $s['id'] ?>" class="student-chk" <?= $isPresent ? 'checked' : '' ?> style="transform: scale(1.3)">
                                </label>
                            </td>
                            <td>
                                <span class="fw-600 text-sm"><?= htmlspecialchars($s['name']) ?></span>
                            </td>
                            <td class="text-sm"><code><?= htmlspecialchars($s['student_id']) ?></code></td>
                            <td class="text-sm"><?= htmlspecialchars($s['dept_name'] ?? 'N/A') ?></td>
                            <td>
                                <?= statusBadge($status) ?>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-footer d-flex justify-end" style="padding: 16px 24px">
                <button type="submit" class="btn btn-success">
                    <i class="fa fa-save"></i> Save Attendance Sheet
                </button>
            </div>
        </div>
    </form>

</main>
</div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const checkboxes = document.querySelectorAll('.student-chk');
    
    document.getElementById('btn-select-all')?.addEventListener('click', function () {
        checkboxes.forEach(chk => chk.checked = true);
    });

    document.getElementById('btn-deselect-all')?.addEventListener('click', function () {
        checkboxes.forEach(chk => chk.checked = false);
    });
});
</script>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
