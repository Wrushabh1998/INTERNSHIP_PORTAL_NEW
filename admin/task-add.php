<?php
/**
 * Admin — Add/Edit Task
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/helpers/upload.php';

requireAdmin();
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
$task = null;

$adminUser = getCurrentAdmin();
$isMentor  = $adminUser && ($adminUser['role'] ?? 'mentor') === 'mentor';
$mentorId  = $_SESSION['admin_id'];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$id]);
    $task = $stmt->fetch();
    if ($task && $isMentor) {
        $chkStud = $pdo->prepare("SELECT COUNT(*) FROM students WHERE id = ? AND mentor_id = ?");
        $chkStud->execute([$task['student_id'], $mentorId]);
        if ((int)$chkStud->fetchColumn() === 0) {
            setFlash('error', 'Access denied to this task.');
            redirect(SITE_URL . '/admin/tasks.php');
        }
    }
}

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

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $title       = clean($_POST['title'] ?? '');
        $description = clean($_POST['description'] ?? '');
        $student_id  = (int)($_POST['student_id'] ?? 0);
        $project_id  = (int)($_POST['project_id'] ?? 0) ?: null;
        $priority    = in_array($_POST['priority']??'',['Low','Medium','High','Critical']) ? $_POST['priority'] : 'Medium';
        $deadline    = $_POST['deadline'] ?? null;
        $status      = in_array($_POST['status']??'',['Pending','In Progress','Completed','Verified']) ? $_POST['status'] : 'Pending';
        $progress    = min(100, max(0, (int)($_POST['progress'] ?? 0)));

        if (!$title) {
            $errors[] = 'Task title is required.';
        }
        if (!$student_id) {
            $errors[] = 'Please assign a student.';
        }

        // Handle attachment upload
        $attachment = $task['attachment'] ?? null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            $dest = UPLOAD_DOCUMENTS;
            $up = uploadFile($_FILES['attachment'], $dest, ALLOWED_DOC_TYPES, MAX_FILE_SIZE, 'task');
            if ($up['success']) {
                $attachment = $up['filename'];
            } else {
                $errors[] = 'Attachment upload error: ' . $up['error'];
            }
        }

        if (empty($errors)) {
            if ($id) {
                // Update
                $stmt = $pdo->prepare("
                    UPDATE tasks 
                    SET project_id=?, student_id=?, title=?, description=?, priority=?, deadline=?, status=?, progress=?, attachment=?
                    WHERE id=?
                ");
                $stmt->execute([$project_id, $student_id, $title, $description, $priority, $deadline ?: null, $status, $progress, $attachment, $id]);
                logActivity('admin', $_SESSION['admin_id'], 'edit_task', "Updated task: $title ($id)");
                setFlash('success', 'Task updated successfully.');
            } else {
                // Create
                $stmt = $pdo->prepare("
                    INSERT INTO tasks (project_id, student_id, title, description, priority, deadline, status, progress, attachment, assigned_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$project_id, $student_id, $title, $description, $priority, $deadline ?: null, $status, $progress, $attachment, $_SESSION['admin_id']]);
                $newTaskId = $pdo->lastInsertId();

                createNotification($student_id, 'Task', 'New Task Assigned', "You have been assigned a new task: $title", 'student/tasks.php');
                logActivity('admin', $_SESSION['admin_id'], 'add_task', "Assigned task: $title to student ID: $student_id");
                setFlash('success', 'Task allocated successfully.');
            }
            redirect(SITE_URL . '/admin/tasks.php');
        }
    }
}

$pageTitle  = $id ? 'Edit Task' : 'Allocate Task';
$userRole   = 'admin';
$breadcrumb = [['label'=>'Tasks','url'=>SITE_URL.'/admin/tasks.php'],['label'=>$id?'Edit Task':'Allocate Task']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-admin.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <div>
            <h1 class="page-title"><?= $id ? 'Edit Task Assignment' : 'Allocate New Task' ?></h1>
            <p class="page-subtitle"><?= $id ? 'Update task requirements or progress' : 'Assign specific targets or modules to interns' ?></p>
        </div>
        <a href="<?= SITE_URL ?>/admin/tasks.php" class="btn btn-secondary">
            <i class="fa fa-arrow-left"></i> Back
        </a>
    </div>

    <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <div class="card" style="max-width:700px;margin:0 auto">
        <div class="card-header"><span class="card-title"><i class="fa fa-tasks"></i> Task Parameters</span></div>
        <div class="card-body">
            <form method="POST" enctype="multipart/form-data">
                <?= csrfField() ?>

                <div class="form-group">
                    <label class="form-label">Task Name / Summary <span class="required">*</span></label>
                    <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($task['title'] ?? '') ?>" placeholder="e.g. Implement User Authentication">
                </div>

                <div class="form-group">
                    <label class="form-label">Task Description</label>
                    <textarea name="description" class="form-control" rows="4" placeholder="Detail the steps or expectations for this task…"><?= htmlspecialchars($task['description'] ?? '') ?></textarea>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Assign To Intern <span class="required">*</span></label>
                        <select name="student_id" class="form-control" required>
                            <option value="">— Select Intern —</option>
                            <?php foreach ($students as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= ($task['student_id']??0)==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['student_id']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Project Association</label>
                        <select name="project_id" class="form-control">
                            <option value="">— Independent / None —</option>
                            <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>" <?= ($task['project_id']??0)==$p['id']?'selected':'' ?>><?= htmlspecialchars($p['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Priority</label>
                        <select name="priority" class="form-control">
                            <?php foreach (['Low','Medium','High','Critical'] as $pr): ?>
                            <option value="<?= $pr ?>" <?= ($task['priority']??'Medium')===$pr?'selected':'' ?>><?= $pr ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Deadline</label>
                        <input type="date" name="deadline" class="form-control" value="<?= $task['deadline'] ?? '' ?>">
                    </div>
                </div>

                <?php if ($id): ?>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Status</label>
                        <select name="status" class="form-control">
                            <?php foreach (['Pending','In Progress','Completed','Verified'] as $st): ?>
                            <option value="<?= $st ?>" <?= ($task['status']??'Pending')===$st?'selected':'' ?>><?= $st ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Progress: <span id="prog-val"><?= $task['progress'] ?? 0 ?></span>%</label>
                        <input type="range" name="progress" id="prog-slider" class="w-100" min="0" max="100" step="5" value="<?= $task['progress'] ?? 0 ?>" style="accent-color:var(--primary)">
                    </div>
                </div>
                <?php endif; ?>

                <div class="form-group">
                    <label class="form-label">Reference Attachment (optional)</label>
                    <input type="file" name="attachment" class="form-control">
                    <?php if (!empty($task['attachment'])): ?>
                    <div class="form-text">Current Attachment: <a href="<?= SITE_URL ?>/uploads/documents/<?= htmlspecialchars($task['attachment']) ?>" target="_blank"><?= htmlspecialchars($task['attachment']) ?></a></div>
                    <?php endif; ?>
                </div>

                <div class="d-flex justify-between align-center mt-24">
                    <a href="<?= SITE_URL ?>/admin/tasks.php" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">
                        <i class="fa fa-save"></i> <?= $id ? 'Save Changes' : 'Allocate Task' ?>
                    </button>
                </div>

            </form>
        </div>
    </div>

</main>
</div>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
<script>
const range = document.getElementById('prog-slider');
const val   = document.getElementById('prog-val');
if (range && val) {
    range.addEventListener('input', function() {
        val.textContent = this.value;
    });
}
</script>
