<?php
/**
 * Admin — Task Management
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin();
$pdo = db();

$action = $_GET['action'] ?? '';

// Verify/Approve Task
if ($action === 'verify' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pdo->prepare("UPDATE tasks SET status='Verified', verified_by=?, verified_at=NOW() WHERE id=?")->execute([$_SESSION['admin_id'], $id]);
    
    // Get task student
    $t = $pdo->prepare("SELECT student_id, title FROM tasks WHERE id = ?");
    $t->execute([$id]);
    $task = $t->fetch();
    if ($task) {
        createNotification($task['student_id'], 'Task', 'Task Verified', "Your task '{$task['title']}' has been verified by the admin.", 'student/tasks.php');
    }

    logActivity('admin', $_SESSION['admin_id'], 'verify_task', "Verified task ID: $id");
    setFlash('success', 'Task marked as Verified.');
    redirect(SITE_URL . '/admin/tasks.php');
}

// Delete Task
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pdo->prepare("DELETE FROM tasks WHERE id=?")->execute([$id]);
    logActivity('admin', $_SESSION['admin_id'], 'delete_task', "Deleted task ID: $id");
    setFlash('success', 'Task deleted successfully.');
    redirect(SITE_URL . '/admin/tasks.php');
}

// Filters
$studentId  = (int)($_GET['student'] ?? 0);
$projectId  = (int)($_GET['project'] ?? 0);
$statusF    = clean($_GET['status'] ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));

$adminUser = getCurrentAdmin();
$isMentor  = $adminUser && ($adminUser['role'] ?? 'mentor') === 'mentor';
$mentorId  = $_SESSION['admin_id'];

$where = ['1=1'];
$params = [];
if ($studentId) { $where[] = 't.student_id=?'; $params[] = $studentId; }
if ($projectId) { $where[] = 't.project_id=?'; $params[] = $projectId; }
if ($statusF)   { $where[] = 't.status=?'; $params[] = $statusF; }

if ($isMentor) {
    $where[] = 't.student_id IN (SELECT id FROM students WHERE mentor_id = ?)';
    $params[] = $mentorId;
}

$whereStr = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks t WHERE $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$pag = paginate($total, $page);

// Fetch tasks
$tasks = $pdo->prepare("
    SELECT t.*, s.name as student_name, s.student_id as sid, p.title as project_title
    FROM tasks t
    JOIN students s ON t.student_id = s.id
    LEFT JOIN projects p ON t.project_id = p.id
    WHERE $whereStr
    ORDER BY t.created_at DESC
    LIMIT {$pag['per_page']} OFFSET {$pag['offset']}
");
$tasks->execute($params);
$tasks = $tasks->fetchAll();

if ($isMentor) {
    $stmt_studs = $pdo->prepare("SELECT id, name, student_id FROM students WHERE is_active=1 AND mentor_id=? ORDER BY name");
    $stmt_studs->execute([$mentorId]);
    
    $stmt_projs = $pdo->prepare("SELECT id, title FROM projects WHERE created_by=? OR id IN (SELECT project_id FROM project_assignments pa JOIN students s ON pa.student_id=s.id WHERE s.mentor_id=?) ORDER BY title");
    $stmt_projs->execute([$mentorId, $mentorId]);
} else {
    $stmt_studs = $pdo->prepare("SELECT id, name, student_id FROM students WHERE is_active=1 ORDER BY name");
    $stmt_studs->execute();
    
    $stmt_projs = $pdo->prepare("SELECT id, title FROM projects ORDER BY title");
    $stmt_projs->execute();
}
$students = $stmt_studs->fetchAll();
$projects = $stmt_projs->fetchAll();

$pageTitle  = 'Tasks';
$userRole   = 'admin';
$breadcrumb = [['label'=>'Tasks']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-admin.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">Task Allocation</h1>
            <p class="page-subtitle">Track and assign daily/weekly tasks for interns</p>
        </div>
        <a href="<?= SITE_URL ?>/admin/task-add.php" class="btn btn-primary">
            <i class="fa fa-plus"></i> Allocate Task
        </a>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:20px">
        <div class="card-body" style="padding:16px 22px">
            <form method="GET" class="d-flex gap-12 align-center flex-wrap">
                <select name="student" class="form-control" style="width:200px">
                    <option value="">All Students</option>
                    <?php foreach ($students as $s): ?>
                    <option value="<?= $s['id'] ?>" <?= $studentId==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['student_id']) ?>)</option>
                    <?php endforeach; ?>
                </select>
                <select name="project" class="form-control" style="width:200px">
                    <option value="">All Projects</option>
                    <?php foreach ($projects as $p): ?>
                    <option value="<?= $p['id'] ?>" <?= $projectId==$p['id']?'selected':'' ?>><?= htmlspecialchars($p['title']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status" class="form-control" style="width:160px">
                    <option value="">All Status</option>
                    <?php foreach (['Pending','In Progress','Completed','Verified'] as $st): ?>
                    <option <?= $statusF===$st?'selected':'' ?>><?= $st ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Filter</button>
                <a href="<?= SITE_URL ?>/admin/tasks.php" class="btn btn-secondary btn-sm">Reset</a>
            </form>
        </div>
    </div>

    <!-- Task List Table -->
    <div class="card table-card">
        <div class="card-header">
            <span class="card-title">Allocated Tasks</span>
            <span class="text-sm text-muted"><?= $total ?> task(s) allocated</span>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Task Details</th>
                        <th>Assigned To</th>
                        <th>Project</th>
                        <th>Priority</th>
                        <th>Deadline</th>
                        <th>Progress</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($tasks)): ?>
                    <tr><td colspan="9">
                        <div class="empty-state">
                            <div class="empty-icon">📋</div>
                            <div class="empty-title">No tasks allocated</div>
                            <div class="empty-msg">No tasks match your criteria or none have been assigned yet.</div>
                        </div>
                    </td></tr>
                    <?php else: foreach ($tasks as $i => $t):
                        $prioClasses = ['Low'=>'success','Medium'=>'info','High'=>'warning','Critical'=>'danger'];
                        $prioClass   = $prioClasses[$t['priority']] ?? 'secondary';
                        $overdue = $t['deadline'] && $t['deadline'] < date('Y-m-d') && $t['status'] !== 'Verified';
                    ?>
                    <tr>
                        <td class="text-muted text-sm"><?= $pag['offset'] + $i + 1 ?></td>
                        <td>
                            <div class="fw-600 text-sm"><?= htmlspecialchars($t['title']) ?></div>
                            <div class="text-xs text-muted" style="max-width:240px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap" title="<?= htmlspecialchars($t['description']) ?>"><?= htmlspecialchars($t['description']) ?></div>
                        </td>
                        <td>
                            <div class="fw-600 text-sm"><?= htmlspecialchars($t['student_name']) ?></div>
                            <div class="text-xs text-muted"><?= htmlspecialchars($t['sid']) ?></div>
                        </td>
                        <td class="text-sm"><?= htmlspecialchars($t['project_title'] ?? 'None') ?></td>
                        <td><span class="badge badge-<?= $prioClass ?>"><?= $t['priority'] ?></span></td>
                        <td class="text-sm <?= $overdue ? 'text-danger fw-600' : '' ?>">
                            <?= $t['deadline'] ? formatDate($t['deadline']) : 'None' ?>
                            <?= $overdue ? '<br><span class="badge badge-danger text-xs">Overdue</span>' : '' ?>
                        </td>
                        <td>
                            <div class="d-flex align-center gap-6">
                                <div class="progress" style="width:60px">
                                    <div class="progress-bar" data-value="<?= $t['progress'] ?>" style="width:<?= $t['progress'] ?>%"></div>
                                </div>
                                <span class="text-xs fw-600"><?= $t['progress'] ?>%</span>
                            </div>
                        </td>
                        <td><?= statusBadge($t['status']) ?></td>
                        <td>
                            <div class="td-actions">
                                <a href="<?= SITE_URL ?>/admin/task-add.php?id=<?= $t['id'] ?>" class="btn btn-ghost btn-icon-sm" title="Edit">
                                    <i class="fa fa-edit"></i>
                                </a>
                                <?php if ($t['status'] === 'Completed'): ?>
                                <a href="?action=verify&id=<?= $t['id'] ?>" class="btn btn-success btn-icon-sm" title="Verify & Close">
                                    <i class="fa fa-check-double"></i>
                                </a>
                                <?php endif; ?>
                                <a href="?action=delete&id=<?= $t['id'] ?>" class="btn btn-danger btn-icon-sm" title="Delete" data-confirm-delete="Delete this task allocation?">
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
            <?= renderPagination($pag, SITE_URL . '/admin/tasks.php?' . http_build_query(['student'=>$studentId,'project'=>$projectId,'status'=>$statusF])) ?>
        </div>
    </div>

</main>
</div>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
