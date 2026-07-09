<?php
/**
 * Admin — Project Management
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin();
$pdo = db();

$action = $_GET['action'] ?? '';

// Handle Delete Project
if ($action === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pdo->prepare("DELETE FROM projects WHERE id=?")->execute([$id]);
    logActivity('admin', $_SESSION['admin_id'], 'delete_project', "Deleted project ID: $id");
    setFlash('success', 'Project deleted successfully.');
    redirect(SITE_URL . '/admin/projects.php');
}

// Fetch all projects
$adminUser = getCurrentAdmin();
$isMentor  = $adminUser && ($adminUser['role'] ?? 'mentor') === 'mentor';
$mentorId  = $_SESSION['admin_id'];

if ($isMentor) {
    $projects = $pdo->prepare("
        SELECT p.*, adm.name as creator_name,
               (SELECT COUNT(*) FROM project_assignments WHERE project_id = p.id) as assigned_count
        FROM projects p
        LEFT JOIN admin adm ON p.created_by = adm.id
        WHERE p.created_by = ? OR p.id IN (
            SELECT project_id FROM project_assignments pa JOIN students s ON pa.student_id = s.id WHERE s.mentor_id = ?
        )
        ORDER BY p.created_at DESC
    ");
    $projects->execute([$mentorId, $mentorId]);
} else {
    $projects = $pdo->prepare("
        SELECT p.*, adm.name as creator_name,
               (SELECT COUNT(*) FROM project_assignments WHERE project_id = p.id) as assigned_count
        FROM projects p
        LEFT JOIN admin adm ON p.created_by = adm.id
        ORDER BY p.created_at DESC
    ");
    $projects->execute();
}
$projects = $projects->fetchAll();

$pageTitle  = 'Projects';
$userRole   = 'admin';
$breadcrumb = [['label'=>'Projects']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-admin.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">Project Management</h1>
            <p class="page-subtitle">Manage group or individual internship projects</p>
        </div>
        <a href="<?= SITE_URL ?>/admin/project-add.php" class="btn btn-primary">
            <i class="fa fa-plus"></i> Create Project
        </a>
    </div>

    <!-- Grid Layout of Projects -->
    <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(360px, 1fr));gap:20px">
        <?php if (empty($projects)): ?>
        <div class="card w-100" style="grid-column: 1 / -1">
            <div class="card-body">
                <div class="empty-state">
                    <div class="empty-icon">📁</div>
                    <div class="empty-title">No projects created yet</div>
                    <div class="empty-msg">Click "Create Project" to get started.</div>
                </div>
            </div>
        </div>
        <?php else: foreach ($projects as $p):
            $prioClasses = ['Low'=>'success','Medium'=>'info','High'=>'warning','Critical'=>'danger'];
            $prioClass   = $prioClasses[$p['priority']] ?? 'secondary';
        ?>
        <div class="card">
            <div class="card-body">
                <div class="d-flex justify-between align-center mb-12">
                    <span class="badge badge-<?= $prioClass ?>"><?= $p['priority'] ?> Priority</span>
                    <?= statusBadge($p['status']) ?>
                </div>
                <h3 style="font-size:1.1rem;margin-bottom:8px"><?= htmlspecialchars($p['title']) ?></h3>
                <p class="text-sm text-muted mb-12" style="height:50px;overflow:hidden;text-overflow:ellipsis">
                    <?= htmlspecialchars($p['description'] ?? 'No description provided.') ?>
                </p>
                <div class="text-xs text-muted mb-12">
                    <strong>Tech Stack:</strong> <code style="font-size:.78rem;background:var(--bg-table-head);padding:2px 6px;border-radius:4px"><?= htmlspecialchars($p['tech_stack'] ?? 'None') ?></code>
                </div>

                <div class="d-flex justify-between align-center text-xs text-muted mb-12">
                    <span><i class="fa fa-calendar"></i> <?= $p['start_date'] ? formatDate($p['start_date']) : 'N/A' ?> – <?= $p['end_date'] ? formatDate($p['end_date']) : 'N/A' ?></span>
                    <span><i class="fa fa-users"></i> <?= $p['assigned_count'] ?> assigned</span>
                </div>

                <!-- Progress -->
                <div class="d-flex align-center gap-8 mb-16">
                    <div class="progress" style="flex:1">
                        <div class="progress-bar" data-value="<?= $p['completion'] ?>" style="width:<?= $p['completion'] ?>%"></div>
                    </div>
                    <span class="text-xs fw-600"><?= $p['completion'] ?>%</span>
                </div>

                <hr class="divider" style="margin:12px 0">

                <div class="d-flex justify-between align-center">
                    <span class="text-xs text-muted">Created by: <?= htmlspecialchars($p['creator_name'] ?? 'System') ?></span>
                    <div class="td-actions">
                        <a href="<?= SITE_URL ?>/admin/project-add.php?id=<?= $p['id'] ?>" class="btn btn-ghost btn-icon-sm" title="Edit">
                            <i class="fa fa-edit"></i>
                        </a>
                        <a href="?action=delete&id=<?= $p['id'] ?>" class="btn btn-danger btn-icon-sm" title="Delete" data-confirm-delete="Are you sure you want to delete this project? All assignments and milestones will be removed.">
                            <i class="fa fa-trash"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>

</main>
</div>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
