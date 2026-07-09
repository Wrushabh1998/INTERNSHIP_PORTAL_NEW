<?php
/**
 * Admin — Add/Edit Project
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin();
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
$project = null;
$assignedStudents = [];

$adminUser = getCurrentAdmin();
$isMentor  = $adminUser && ($adminUser['role'] ?? 'mentor') === 'mentor';
$mentorId  = $_SESSION['admin_id'];

if ($id) {
    $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ?");
    $stmt->execute([$id]);
    $project = $stmt->fetch();
    if ($project) {
        if ($isMentor && (int)$project['created_by'] !== (int)$mentorId) {
            $chkAssign = $pdo->prepare("SELECT COUNT(*) FROM project_assignments pa JOIN students s ON pa.student_id = s.id WHERE pa.project_id = ? AND s.mentor_id = ?");
            $chkAssign->execute([$id, $mentorId]);
            if ((int)$chkAssign->fetchColumn() === 0) {
                setFlash('error', 'Access denied to this project.');
                redirect(SITE_URL . '/admin/projects.php');
            }
        }
        $stmt_assigned = $pdo->prepare("SELECT student_id FROM project_assignments WHERE project_id = ?");
        $stmt_assigned->execute([$id]);
        $assignedStudents = $stmt_assigned->fetchAll(PDO::FETCH_COLUMN);
    }
}

if ($isMentor) {
    $stmt_students = $pdo->prepare("SELECT id, name, student_id FROM students WHERE is_active=1 AND mentor_id=? ORDER BY name");
    $stmt_students->execute([$mentorId]);
} else {
    $stmt_students = $pdo->prepare("SELECT id, name, student_id FROM students WHERE is_active=1 ORDER BY name");
    $stmt_students->execute();
}
$students = $stmt_students->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $title       = clean($_POST['title'] ?? '');
        $description = clean($_POST['description'] ?? '');
        $tech_stack  = clean($_POST['tech_stack'] ?? '');
        $start_date  = $_POST['start_date'] ?? null;
        $end_date    = $_POST['end_date'] ?? null;
        $priority    = in_array($_POST['priority']??'',['Low','Medium','High','Critical']) ? $_POST['priority'] : 'Medium';
        $status      = in_array($_POST['status']??'',['Planning','Active','On Hold','Completed','Cancelled']) ? $_POST['status'] : 'Planning';
        $completion  = min(100, max(0, (int)($_POST['completion'] ?? 0)));
        $selStudents = $_POST['students'] ?? [];

        if (!$title) {
            $errors[] = 'Project title is required.';
        }

        if (empty($errors)) {
            if ($id) {
                // Update
                $stmt = $pdo->prepare("
                    UPDATE projects 
                    SET title=?, description=?, tech_stack=?, start_date=?, end_date=?, priority=?, status=?, completion=?
                    WHERE id=?
                ");
                $stmt->execute([$title, $description, $tech_stack, $start_date ?: null, $end_date ?: null, $priority, $status, $completion, $id]);

                // Sync assignments
                $pdo->prepare("DELETE FROM project_assignments WHERE project_id=?")->execute([$id]);
                if (!empty($selStudents)) {
                    $ins = $pdo->prepare("INSERT INTO project_assignments (project_id, student_id) VALUES (?, ?)");
                    foreach ($selStudents as $sid) {
                        $ins->execute([$id, (int)$sid]);
                    }
                }
                logActivity('admin', $_SESSION['admin_id'], 'edit_project', "Updated project: $title ($id)");
                setFlash('success', 'Project updated successfully.');
            } else {
                // Create
                $stmt = $pdo->prepare("
                    INSERT INTO projects (title, description, tech_stack, start_date, end_date, priority, status, completion, created_by)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$title, $description, $tech_stack, $start_date ?: null, $end_date ?: null, $priority, $status, $completion, $_SESSION['admin_id']]);
                $newProjId = $pdo->lastInsertId();

                if (!empty($selStudents)) {
                    $ins = $pdo->prepare("INSERT INTO project_assignments (project_id, student_id) VALUES (?, ?)");
                    foreach ($selStudents as $sid) {
                        $ins->execute([$newProjId, (int)$sid]);
                        createNotification((int)$sid, 'General', 'Assigned to Project', "You have been assigned to project: $title", 'student/projects.php');
                    }
                }
                logActivity('admin', $_SESSION['admin_id'], 'add_project', "Created project: $title");
                setFlash('success', 'Project created successfully.');
            }
            redirect(SITE_URL . '/admin/projects.php');
        }
    }
}

$pageTitle  = $id ? 'Edit Project' : 'Create Project';
$userRole   = 'admin';
$breadcrumb = [['label'=>'Projects','url'=>SITE_URL.'/admin/projects.php'],['label'=>$id?'Edit Project':'Create Project']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-admin.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <div>
            <h1 class="page-title"><?= $id ? 'Edit Project' : 'Create New Project' ?></h1>
            <p class="page-subtitle"><?= $id ? 'Modify details of project: '.htmlspecialchars($project['title']) : 'Define a new project and assign students' ?></p>
        </div>
        <a href="<?= SITE_URL ?>/admin/projects.php" class="btn btn-secondary">
            <i class="fa fa-arrow-left"></i> Back
        </a>
    </div>

    <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <form method="POST">
        <?= csrfField() ?>
        <div style="display:grid;grid-template-columns:2fr 1.2fr;gap:20px">

            <!-- Project General Details -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fa fa-info-circle"></i> Project Details</span></div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Project Title <span class="required">*</span></label>
                        <input type="text" name="title" class="form-control" required value="<?= htmlspecialchars($project['title'] ?? '') ?>" placeholder="e.g. E-Commerce Backend">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="5" placeholder="Provide a brief summary of requirements and targets…"><?= htmlspecialchars($project['description'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Tech Stack (comma separated)</label>
                        <input type="text" name="tech_stack" class="form-control" value="<?= htmlspecialchars($project['tech_stack'] ?? '') ?>" placeholder="e.g. PHP, MySQL, JavaScript, CSS">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Start Date</label>
                            <input type="date" name="start_date" class="form-control" value="<?= $project['start_date'] ?? '' ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">End Date</label>
                            <input type="date" name="end_date" class="form-control" value="<?= $project['end_date'] ?? '' ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Priority</label>
                            <select name="priority" class="form-control">
                                <?php foreach (['Low','Medium','High','Critical'] as $p): ?>
                                <option value="<?= $p ?>" <?= ($project['priority']??'Medium')===$p?'selected':'' ?>><?= $p ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-control">
                                <?php foreach (['Planning','Active','On Hold','Completed','Cancelled'] as $s): ?>
                                <option value="<?= $s ?>" <?= ($project['status']??'Planning')===$s?'selected':'' ?>><?= $s ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <?php if ($id): ?>
                    <div class="form-group">
                        <label class="form-label">Overall Completion: <span id="comp-val"><?= $project['completion'] ?? 0 ?></span>%</label>
                        <input type="range" name="completion" id="comp-range" class="w-100" min="0" max="100" step="5" value="<?= $project['completion'] ?? 0 ?>" style="accent-color:var(--primary)">
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Assigned Students -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fa fa-users"></i> Assign Students</span></div>
                <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                    <p class="text-xs text-muted mb-12">Select the interns to assign to this project:</p>
                    <?php if (empty($students)): ?>
                    <p class="text-sm text-muted">No active students available.</p>
                    <?php else: foreach ($students as $s): ?>
                    <label class="checkbox-label" style="display:flex;padding:6px 0;border-bottom:1px solid var(--border)">
                        <input type="checkbox" name="students[]" value="<?= $s['id'] ?>" <?= in_array($s['id'], $assignedStudents) ? 'checked' : '' ?>>
                        <div>
                            <div class="fw-600 text-sm"><?= htmlspecialchars($s['name']) ?></div>
                            <div class="text-xs text-muted"><?= htmlspecialchars($s['student_id']) ?></div>
                        </div>
                    </label>
                    <?php endforeach; endif; ?>
                </div>
            </div>

        </div>

        <div class="d-flex justify-between align-center mt-16">
            <a href="<?= SITE_URL ?>/admin/projects.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="fa fa-save"></i> <?= $id ? 'Save Changes' : 'Create Project' ?>
            </button>
        </div>
    </form>

</main>
</div>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
<script>
const range = document.getElementById('comp-range');
const val   = document.getElementById('comp-val');
if (range && val) {
    range.addEventListener('input', function() {
        val.textContent = this.value;
    });
}
</script>
