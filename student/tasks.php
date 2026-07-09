<?php
/**
 * Student — My Tasks
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

requireStudent();
$pdo  = db();
$sId  = $_SESSION['student_id'];

// Handle progress update (AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['ajax'])) {
    header('Content-Type: application/json');
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        jsonResponse(false, 'Invalid token.');
    }
    $taskId   = (int)($_POST['task_id']  ?? 0);
    $progress = min(100, max(0, (int)($_POST['progress'] ?? 0)));
    $note     = clean($_POST['note'] ?? '');
    $status   = $progress >= 100 ? 'Completed' : ($progress > 0 ? 'In Progress' : 'Pending');

    // Verify task belongs to student
    $t = $pdo->prepare("SELECT id FROM tasks WHERE id=? AND student_id=?");
    $t->execute([$taskId, $sId]);
    if (!$t->fetchColumn()) jsonResponse(false, 'Task not found.');

    $pdo->prepare("UPDATE tasks SET progress=?, status=? WHERE id=?")->execute([$progress, $status, $taskId]);
    $pdo->prepare("INSERT INTO task_updates (task_id,updated_by,progress,note) VALUES (?,?,?,?)")->execute([$taskId, $sId, $progress, $note]);

    logActivity('student', $sId, 'update_task', "Task $taskId progress: $progress%");
    jsonResponse(true, 'Task updated successfully!', ['status' => $status, 'progress' => $progress]);
}

// Filters
$statusF = clean($_GET['status'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));

$where  = ['t.student_id=?'];
$params = [$sId];
if ($statusF) { $where[] = 't.status=?'; $params[] = $statusF; }
$whereStr = implode(' AND ', $where);

$cntStmt = $pdo->prepare("SELECT COUNT(*) FROM tasks t WHERE $whereStr");
$cntStmt->execute($params);
$total = (int)$cntStmt->fetchColumn();
$pag   = paginate($total, $page);

$tasks = $pdo->prepare("
    SELECT t.*, p.title project_name
    FROM tasks t LEFT JOIN projects p ON t.project_id=p.id
    WHERE $whereStr ORDER BY FIELD(t.priority,'Critical','High','Medium','Low'), t.deadline ASC
    LIMIT {$pag['per_page']} OFFSET {$pag['offset']}
");
$tasks->execute($params);
$tasks = $tasks->fetchAll();

$pageTitle  = 'My Tasks';
$userRole   = 'student';
$breadcrumb = [['label'=>'Tasks']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-student.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <h1 class="page-title">My Tasks</h1>
        <div class="d-flex gap-8 align-center">
            <?php foreach ([''=>'All','Pending'=>'Pending','In Progress'=>'In Progress','Completed'=>'Completed'] as $val => $label): ?>
            <a href="?status=<?= urlencode($val) ?>" class="btn <?= $statusF===$val?'btn-primary':'btn-ghost' ?> btn-sm"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
    </div>

    <?php if (empty($tasks)): ?>
    <div class="card"><div class="card-body">
        <div class="empty-state"><div class="empty-icon">✅</div><div class="empty-title">No tasks found</div><div class="empty-msg">All clear! Or try a different filter.</div></div>
    </div></div>
    <?php else: ?>

    <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(340px,1fr));gap:18px">
        <?php foreach ($tasks as $t):
            $today    = date('Y-m-d');
            $overdue  = $t['deadline'] && $t['deadline'] < $today && !in_array($t['status'],['Completed','Verified']);
            $daysLeft = $t['deadline'] ? (int)((strtotime($t['deadline']) - strtotime($today)) / 86400) : null;
            $prioClasses = ['Low'=>'success','Medium'=>'info','High'=>'warning','Critical'=>'danger'];
            $prioClass   = $prioClasses[$t['priority']] ?? 'secondary';
        ?>
        <div class="card" style="<?= $overdue ? 'border-color:var(--c-danger);' : '' ?>">
            <div class="card-body" style="padding:18px">
                <div class="d-flex justify-between align-center" style="margin-bottom:10px">
                    <span class="badge badge-<?= $prioClass ?>"><?= $t['priority'] ?></span>
                    <?= statusBadge($t['status']) ?>
                </div>
                <h3 style="font-size:1rem;margin-bottom:6px"><?= htmlspecialchars($t['title']) ?></h3>
                <?php if ($t['project_name']): ?>
                <p class="text-xs text-muted" style="margin-bottom:8px">
                    <i class="fa fa-project-diagram"></i> <?= htmlspecialchars($t['project_name']) ?>
                </p>
                <?php endif; ?>
                <p class="text-sm text-muted" style="margin-bottom:12px">
                    <?= htmlspecialchars(substr($t['description'] ?? '—', 0, 120)) ?>
                </p>

                <!-- Progress Bar -->
                <div class="d-flex align-center gap-8" style="margin-bottom:12px">
                    <div class="progress" style="flex:1">
                        <div class="progress-bar <?= $t['progress']>=100?'success':($t['progress']>=50?'info':'warning') ?>"
                             data-value="<?= $t['progress'] ?>"
                             style="width:<?= $t['progress'] ?>%"></div>
                    </div>
                    <span class="text-sm fw-600"><?= $t['progress'] ?>%</span>
                </div>

                <!-- Deadline -->
                <?php if ($t['deadline']): ?>
                <div class="text-xs <?= $overdue ? 'text-danger' : 'text-muted' ?>" style="margin-bottom:12px">
                    <i class="fa fa-calendar"></i>
                    Deadline: <?= formatDate($t['deadline']) ?>
                    <?php if ($overdue): ?>
                    <span class="badge badge-danger">OVERDUE</span>
                    <?php elseif ($daysLeft !== null && $daysLeft <= 3): ?>
                    <span class="badge badge-warning"><?= $daysLeft ?>d left</span>
                    <?php endif; ?>
                </div>
                <?php endif; ?>

                <!-- Update btn (not for verified) -->
                <?php if (!in_array($t['status'],['Verified'])): ?>
                <button class="btn btn-primary btn-sm w-100" onclick="openTaskUpdate(<?= $t['id'] ?>, <?= $t['progress'] ?>)">
                    <i class="fa fa-edit"></i> Update Progress
                </button>
                <?php else: ?>
                <div class="alert alert-success" style="margin:0;padding:8px 12px;font-size:.8rem">
                    ✅ Verified by Admin
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="mt-16">
        <?= renderPagination($pag, SITE_URL . '/student/tasks.php?status=' . urlencode($statusF)) ?>
    </div>
    <?php endif; ?>

</main>
</div>
</div>

<!-- Task Update Modal -->
<div class="modal-overlay" id="task-modal">
    <div class="modal" style="max-width:460px">
        <div class="modal-header">
            <span class="modal-title">Update Task Progress</span>
            <button class="modal-close">✕</button>
        </div>
        <div class="modal-body">
            <div class="form-group">
                <label class="form-label">Progress: <span id="prog-display">0</span>%</label>
                <input type="range" id="prog-slider" min="0" max="100" step="5" value="0"
                       class="w-100" style="accent-color:var(--primary)">
            </div>
            <div class="form-group">
                <label class="form-label">Update Note</label>
                <textarea id="task-note" class="form-control" rows="3" placeholder="Describe what you did…"></textarea>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" data-modal-close>Cancel</button>
            <button class="btn btn-primary" id="save-task-btn" onclick="saveTaskUpdate()">
                <i class="fa fa-save"></i> Save Update
            </button>
        </div>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
<script>
let currentTaskId = 0;
function openTaskUpdate(id, currentProgress) {
    currentTaskId = id;
    document.getElementById('prog-slider').value = currentProgress;
    document.getElementById('prog-display').textContent = currentProgress;
    Modal.open('task-modal');
}
document.getElementById('prog-slider').addEventListener('input', function() {
    document.getElementById('prog-display').textContent = this.value;
});
function saveTaskUpdate() {
    const btn = document.getElementById('save-task-btn');
    btn.disabled = true; btn.innerHTML = '<span class="spinner spinner-sm"></span>';
    const fd = new FormData();
    fd.append('ajax', '1');
    fd.append('task_id', currentTaskId);
    fd.append('progress', document.getElementById('prog-slider').value);
    fd.append('note', document.getElementById('task-note').value);
    fd.append('_csrf_token', '<?= generateCsrfToken() ?>');
    fetch('<?= SITE_URL ?>/student/tasks.php', { method:'POST', body:fd })
        .then(r=>r.json())
        .then(d=>{
            if(d.success){ Toast.show('success','Updated!',d.message); Modal.close('task-modal'); setTimeout(()=>location.reload(),1200); }
            else Toast.show('error','Error',d.message);
        }).finally(()=>{ btn.disabled=false; btn.innerHTML='<i class="fa fa-save"></i> Save Update'; });
}
</script>
