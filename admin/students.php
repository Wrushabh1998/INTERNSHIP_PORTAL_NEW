<?php
/**
 * Admin — Student Management
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin();
$pdo = db();

// Handle actions
$action = $_GET['action'] ?? '';
$msg = '';

// Delete
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    try {
        $pdo->prepare("DELETE FROM students WHERE id=?")->execute([$id]);
        logActivity('admin', $_SESSION['admin_id'], 'delete_student', "Deleted student ID: $id");
        setFlash('success', 'Student deleted successfully.');
    } catch (Exception $e) {
        setFlash('error', 'Cannot delete: student has related records.');
    }
    redirect(SITE_URL . '/admin/students.php');
}

// Toggle active
if ($action === 'toggle' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pdo->prepare("UPDATE students SET is_active = NOT is_active WHERE id=?")->execute([$id]);
    logActivity('admin', $_SESSION['admin_id'], 'toggle_student', "Toggled active status for student ID: $id");
    setFlash('success', 'Student status updated.');
    redirect(SITE_URL . '/admin/students.php');
}

// Reset password
if ($action === 'reset_pw' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $newPw = 'Student@123';
    $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
    $pdo->prepare("UPDATE students SET password=? WHERE id=?")->execute([$hash, $id]);
    setFlash('success', "Password reset to: $newPw");
    redirect(SITE_URL . '/admin/students.php');
}

// Search & Filter
$search  = clean($_GET['search'] ?? '');
$deptId  = (int)($_GET['dept'] ?? 0);
$batchId = (int)($_GET['batch'] ?? 0);
$status  = $_GET['status'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));

$where = ['1=1'];
$params = [];

if ($search) {
    $where[] = '(s.name LIKE ? OR s.email LIKE ? OR s.student_id LIKE ? OR s.college LIKE ?)';
    $params  = array_merge($params, ["%$search%","%$search%","%$search%","%$search%"]);
}
if ($deptId) { $where[] = 's.department_id=?'; $params[] = $deptId; }
if ($batchId) { $where[] = 's.batch_id=?'; $params[] = $batchId; }
if ($status !== '') { $where[] = 's.is_active=?'; $params[] = (int)$status; }

$adminUser = getCurrentAdmin();
$isMentor  = $adminUser && ($adminUser['role'] ?? 'mentor') === 'mentor';

if ($isMentor) {
    $where[]  = 's.mentor_id=?';
    $params[] = $adminUser['id'];
}

$whereStr = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM students s WHERE $whereStr");
$countStmt->execute($params);
$totalCount = (int)$countStmt->fetchColumn();

$pag = paginate($totalCount, $page);
$students = $pdo->prepare("
    SELECT s.*, d.name dept_name, b.batch_name
    FROM students s
    LEFT JOIN departments d ON s.department_id=d.id
    LEFT JOIN internship_batches b ON s.batch_id=b.id
    WHERE $whereStr
    ORDER BY s.created_at DESC
    LIMIT {$pag['per_page']} OFFSET {$pag['offset']}
");
$students->execute($params);
$students = $students->fetchAll();

$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();
$batches     = $pdo->query("SELECT * FROM internship_batches ORDER BY start_date DESC")->fetchAll();

$pageTitle  = 'Student Management';
$userRole   = 'admin';
$breadcrumb = [['label' => 'Students']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-admin.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">Student Management</h1>
            <p class="page-subtitle"><?= $totalCount ?> student(s) total</p>
        </div>
        <a href="<?= SITE_URL ?>/admin/student-add.php" class="btn btn-primary">
            <i class="fa fa-user-plus"></i> Add New Student
        </a>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:20px">
        <div class="card-body" style="padding:16px 22px">
            <form method="GET" class="d-flex gap-12 align-center flex-wrap">
                <div class="search-box" style="max-width:280px;flex:1">
                    <span class="search-icon"><i class="fa fa-search"></i></span>
                    <input type="text" name="search" class="form-control" placeholder="Search name, email, ID…" value="<?= htmlspecialchars($search) ?>">
                </div>
                <select name="dept" class="form-control" style="width:160px">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $deptId==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="batch" class="form-control" style="width:160px">
                    <option value="">All Batches</option>
                    <?php foreach ($batches as $b): ?>
                    <option value="<?= $b['id'] ?>" <?= $batchId==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['batch_name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status" class="form-control" style="width:130px">
                    <option value="">All Status</option>
                    <option value="1" <?= $status==='1'?'selected':'' ?>>Active</option>
                    <option value="0" <?= $status==='0'?'selected':'' ?>>Inactive</option>
                </select>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Filter</button>
                <a href="<?= SITE_URL ?>/admin/students.php" class="btn btn-secondary btn-sm">Reset</a>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card table-card">
        <div class="card-header">
            <span class="card-title">All Students</span>
            <div class="d-flex gap-8">
                <a href="<?= SITE_URL ?>/admin/reports.php?type=students&export=csv" class="btn btn-ghost btn-sm">
                    <i class="fa fa-file-csv"></i> Export CSV
                </a>
                <button onclick="window.print()" class="btn btn-ghost btn-sm">
                    <i class="fa fa-print"></i> Print
                </button>
            </div>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>ID</th>
                        <th>College / Dept</th>
                        <th>Batch</th>
                        <th>Attendance</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($students)): ?>
                    <tr><td colspan="9">
                        <div class="empty-state">
                            <div class="empty-icon">👥</div>
                            <div class="empty-title">No students found</div>
                            <div class="empty-msg">Try adjusting your search filters</div>
                        </div>
                    </td></tr>
                    <?php else: foreach ($students as $i => $s):
                        $attPct = getAttendancePercentage($s['id']);
                    ?>
                    <tr>
                        <td class="text-muted text-sm"><?= $pag['offset'] + $i + 1 ?></td>
                        <td>
                            <div class="d-flex align-center gap-8">
                                <img src="<?= profilePhotoUrl($s['profile_photo'], 'student') ?>" class="avatar avatar-sm" alt="">
                                <div>
                                    <div class="fw-600 text-sm"><?= htmlspecialchars($s['name']) ?></div>
                                    <div class="text-xs text-muted"><?= htmlspecialchars($s['email']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td><code class="text-sm"><?= htmlspecialchars($s['student_id']) ?></code></td>
                        <td class="text-sm">
                            <div><?= htmlspecialchars($s['college'] ?? '—') ?></div>
                            <div class="text-xs text-muted"><?= htmlspecialchars($s['dept_name'] ?? '—') ?></div>
                        </td>
                        <td class="text-sm"><?= htmlspecialchars($s['batch_name'] ?? '—') ?></td>
                        <td>
                            <div class="d-flex align-center gap-6">
                                <div class="progress" style="width:60px">
                                    <div class="progress-bar <?= $attPct >= 75 ? 'success' : ($attPct >= 50 ? 'warning' : 'danger') ?>"
                                         data-value="<?= $attPct ?>" style="width:<?= $attPct ?>%"></div>
                                </div>
                                <span class="text-xs fw-600"><?= $attPct ?>%</span>
                            </div>
                        </td>
                        <td><?= statusBadge($s['is_active'] ? 'Active' : 'Absent') ?></td>
                        <td class="text-xs text-muted"><?= formatDate($s['created_at']) ?></td>
                        <td>
                            <div class="td-actions">
                                <a href="<?= SITE_URL ?>/admin/student-edit.php?id=<?= $s['id'] ?>" class="btn btn-ghost btn-icon-sm" title="Edit">
                                    <i class="fa fa-edit"></i>
                                </a>
                                <a href="?action=toggle&id=<?= $s['id'] ?>" class="btn btn-warning btn-icon-sm" title="<?= $s['is_active'] ? 'Deactivate' : 'Activate' ?>">
                                    <i class="fa fa-<?= $s['is_active'] ? 'ban' : 'check' ?>"></i>
                                </a>
                                <a href="?action=reset_pw&id=<?= $s['id'] ?>" class="btn btn-info btn-icon-sm" title="Reset Password"
                                   onclick="return confirm('Reset password to Student@123?')">
                                    <i class="fa fa-key"></i>
                                </a>
                                <a href="?action=delete&id=<?= $s['id'] ?>"
                                   class="btn btn-danger btn-icon-sm" title="Delete"
                                   data-confirm-delete="Delete <?= htmlspecialchars($s['name']) ?>? All related data will be lost!">
                                    <i class="fa fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <?= renderPagination($pag, SITE_URL . '/admin/students.php?' . http_build_query(['search'=>$search,'dept'=>$deptId,'batch'=>$batchId,'status'=>$status])) ?>
        </div>
    </div>

</main>
</div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
