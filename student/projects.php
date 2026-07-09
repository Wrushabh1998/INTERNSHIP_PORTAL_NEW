<?php
/**
 * Student — Assigned Projects & Milestones
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

requireStudent();
$pdo = db();
$sId = $_SESSION['student_id'];

// Fetch projects assigned to this student
$projects = $pdo->prepare("
    SELECT p.*, pa.role
    FROM projects p
    JOIN project_assignments pa ON p.id = pa.project_id
    WHERE pa.student_id = ?
    ORDER BY p.created_at DESC
");
$projects->execute([$sId]);
$projects = $projects->fetchAll();

$pageTitle  = 'My Projects';
$userRole   = 'student';
$breadcrumb = [['label'=>'Projects']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-student.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">Assigned Projects</h1>
            <p class="page-subtitle">Track project scope, active milestones, and requirements</p>
        </div>
    </div>

    <div style="display:grid;grid-template-columns: 2fr 1fr; gap:20px">
        <!-- Project list -->
        <div>
            <?php if (empty($projects)): ?>
            <div class="card">
                <div class="card-body">
                    <div class="empty-state">
                        <div class="empty-icon">📁</div>
                        <div class="empty-title">No projects assigned</div>
                        <div class="empty-msg">You have not been assigned to any projects yet.</div>
                    </div>
                </div>
            </div>
            <?php else: foreach ($projects as $p):
                $prioClasses = ['Low'=>'success','Medium'=>'info','High'=>'warning','Critical'=>'danger'];
                $prioClass   = $prioClasses[$p['priority']] ?? 'secondary';

                // Fetch milestones for this project
                $milestones = $pdo->prepare("SELECT * FROM milestones WHERE project_id = ? ORDER BY due_date ASC");
                $milestones->execute([$p['id']]);
                $ms = $milestones->fetchAll();
            ?>
            <div class="card mb-20">
                <div class="card-header">
                    <span class="card-title"><i class="fa fa-project-diagram" style="color:var(--primary)"></i> <?= htmlspecialchars($p['title']) ?></span>
                    <div class="d-flex gap-8 align-center">
                        <span class="badge badge-<?= $prioClass ?>"><?= $p['priority'] ?> Priority</span>
                        <?= statusBadge($p['status']) ?>
                    </div>
                </div>
                <div class="card-body">
                    <div class="mb-12">
                        <strong>My Role:</strong> <span class="badge badge-secondary"><?= htmlspecialchars($p['role'] ?? 'Developer') ?></span>
                    </div>
                    <p class="text-sm text-secondary mb-16"><?= htmlspecialchars($p['description'] ?? 'No description.') ?></p>
                    <div class="text-xs text-muted mb-12"><strong>Tech Stack:</strong> <code><?= htmlspecialchars($p['tech_stack'] ?? 'General') ?></code></div>

                    <!-- Progress -->
                    <div class="d-flex align-center gap-8 mb-16">
                        <div class="progress" style="flex:1">
                            <div class="progress-bar" data-value="<?= $p['completion'] ?>" style="width:<?= $p['completion'] ?>%"></div>
                        </div>
                        <span class="text-xs fw-600"><?= $p['completion'] ?>% Complete</span>
                    </div>

                    <!-- Milestones -->
                    <h3 class="section-title mt-16"><i class="fa fa-flag"></i> Project Milestones</h3>
                    <?php if (empty($ms)): ?>
                    <p class="text-xs text-muted">No milestones established for this project.</p>
                    <?php else: foreach ($ms as $m): ?>
                    <div class="d-flex align-center justify-between text-sm" style="padding:10px 0; border-bottom: 1px solid var(--border)">
                        <div>
                            <div class="fw-600"><?= htmlspecialchars($m['title']) ?></div>
                            <div class="text-xs text-muted">Due: <?= $m['due_date'] ? formatDate($m['due_date']) : 'None' ?></div>
                        </div>
                        <div><?= statusBadge($m['status']) ?></div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>

        <!-- Resources / Mentor -->
        <div>
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fa fa-folder-open"></i> Resources &amp; Guides</span></div>
                <div class="card-body text-sm text-secondary">
                    <p>Review these corporate guides to follow coding rules and version control patterns:</p>
                    <ul style="padding-left: 20px; list-style: disc;">
                        <li class="mb-8"><a href="https://git-scm.com/doc" target="_blank">Git Version Control Best Practices</a></li>
                        <li class="mb-8"><a href="https://phptherightway.com/" target="_blank">PHP Design Standards Guide</a></li>
                        <li><a href="https://developer.mozilla.org/en-US/docs/Web/Guide" target="_blank">MDN Front-End Architecture Standards</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</main>
</div>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
