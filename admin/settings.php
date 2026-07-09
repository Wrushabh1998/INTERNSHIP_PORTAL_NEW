<?php
/**
 * Admin — System Settings
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin();
$pdo = db();

$adminUser = getCurrentAdmin();
if ($adminUser && ($adminUser['role'] ?? 'mentor') === 'mentor') {
    setFlash('error', 'Access denied. Super Admin privileges required.');
    redirect(SITE_URL . '/admin/dashboard.php');
}

$errors = [];

// Handle settings update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_settings') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $settings = $_POST['settings'] ?? [];
        $up = $pdo->prepare("UPDATE settings SET value=? WHERE key_name=?");
        foreach ($settings as $key => $val) {
            $up->execute([clean($val), clean($key)]);
        }
        logActivity('admin', $_SESSION['admin_id'], 'update_settings', "Updated system configurations");
        setFlash('success', 'Settings updated successfully.');
        redirect(SITE_URL . '/admin/settings.php');
    }
}

// Handle Add Department
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_dept') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $name = clean($_POST['dept_name'] ?? '');
        $desc = clean($_POST['dept_desc'] ?? '');
        if (!$name) {
            $errors[] = 'Department name is required.';
        } else {
            $stmt = $pdo->prepare("INSERT INTO departments (name, description) VALUES (?, ?)");
            $stmt->execute([$name, $desc]);
            logActivity('admin', $_SESSION['admin_id'], 'add_department', "Added department: $name");
            setFlash('success', 'Department added successfully.');
            redirect(SITE_URL . '/admin/settings.php');
        }
    }
}

// Handle Delete Department
if (isset($_GET['delete_dept'])) {
    $deptId = (int)$_GET['delete_dept'];
    try {
        $pdo->prepare("DELETE FROM departments WHERE id=?")->execute([$deptId]);
        logActivity('admin', $_SESSION['admin_id'], 'delete_department', "Deleted department ID: $deptId");
        setFlash('success', 'Department deleted successfully.');
    } catch (Exception $e) {
        setFlash('error', 'Cannot delete department. There are active students assigned to it.');
    }
    redirect(SITE_URL . '/admin/settings.php');
}

// Fetch current configurations
$configRows = $pdo->query("SELECT * FROM settings")->fetchAll();
$configs    = array_column($configRows, 'value', 'key_name');
$configDescs = array_column($configRows, 'description', 'key_name');

// Fetch departments
$departments = $pdo->query("
    SELECT d.*, (SELECT COUNT(*) FROM students WHERE department_id = d.id) as student_count
    FROM departments d
    ORDER BY d.name
")->fetchAll();

$pageTitle  = 'Settings';
$userRole   = 'admin';
$breadcrumb = [['label'=>'Settings']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-admin.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">System Settings</h1>
            <p class="page-subtitle">Configure office hours, departments, and system defaults</p>
        </div>
    </div>

    <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <!-- Tabs Nav -->
    <div class="tab-nav">
        <button class="tab-btn active" data-tab="config-tab">System Configuration</button>
        <button class="tab-btn" data-tab="dept-tab">Departments (<?= count($departments) ?>)</button>
    </div>

    <!-- Configuration Settings Tab -->
    <div class="tab-content active" id="config-tab">
        <div class="card" style="max-width:720px">
            <div class="card-header"><span class="card-title"><i class="fa fa-sliders-h"></i> System Settings</span></div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="save_settings">

                    <h3 class="section-title">⏱️ Office Hours &amp; Break Durations</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Office Start Time</label>
                            <input type="time" name="settings[office_start]" class="form-control" value="<?= htmlspecialchars($configs['office_start'] ?? '09:00') ?>">
                            <div class="form-text">Expected punch-in opening window.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Late Penalty Time</label>
                            <input type="time" name="settings[late_after]" class="form-control" value="<?= htmlspecialchars($configs['late_after'] ?? '09:30') ?>">
                            <div class="form-text">Intern marked late after this hour.</div>
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Lunch Duration (Minutes)</label>
                            <input type="number" name="settings[lunch_duration]" class="form-control" value="<?= htmlspecialchars($configs['lunch_duration'] ?? '60') ?>">
                            <div class="form-text">Deducted from daily hours calculation.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Standard Full Day Hours</label>
                            <input type="number" name="settings[full_day_hours]" class="form-control" value="<?= htmlspecialchars($configs['full_day_hours'] ?? '8') ?>">
                            <div class="form-text">Hours required for full-day status.</div>
                        </div>
                    </div>

                    <hr class="divider">

                    <h3 class="section-title">🏢 Organization Setup</h3>
                    <div class="form-group">
                        <label class="form-label">Portal / Company Name</label>
                        <input type="text" name="settings[site_name]" class="form-control" value="<?= htmlspecialchars($configs['site_name'] ?? 'InternTrack Pro') ?>">
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Max Casual Leaves (Yearly)</label>
                            <input type="number" name="settings[max_leave_casual]" class="form-control" value="<?= htmlspecialchars($configs['max_leave_casual'] ?? '12') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Max Sick Leaves (Yearly)</label>
                            <input type="number" name="settings[max_leave_sick]" class="form-control" value="<?= htmlspecialchars($configs['max_leave_sick'] ?? '6') ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mt-16"><i class="fa fa-save"></i> Save Settings</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Departments Configuration Tab -->
    <div class="tab-content" id="dept-tab">
        <div style="display:grid;grid-template-columns: 1.2fr 2fr; gap:20px">
            <!-- Add Department Form -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fa fa-plus"></i> Add Department</span></div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="add_dept">
                        <div class="form-group">
                            <label class="form-label">Department Name <span class="required">*</span></label>
                            <input type="text" name="dept_name" class="form-control" required placeholder="e.g. Mobile Development">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Description</label>
                            <textarea name="dept_desc" class="form-control" rows="3" placeholder="Description of the department…"></textarea>
                        </div>
                        <button type="submit" class="btn btn-primary w-100"><i class="fa fa-save"></i> Add</button>
                    </form>
                </div>
            </div>

            <!-- List Departments -->
            <div class="card table-card">
                <div class="card-header"><span class="card-title">Existing Departments</span></div>
                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Description</th>
                                <th>Active Students</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($departments as $d): ?>
                            <tr>
                                <td class="fw-600 text-sm"><?= htmlspecialchars($d['name']) ?></td>
                                <td class="text-xs text-muted"><?= htmlspecialchars($d['description'] ?? 'No description') ?></td>
                                <td><strong><?= $d['student_count'] ?></strong> student(s)</td>
                                <td>
                                    <a href="?delete_dept=<?= $d['id'] ?>" class="btn btn-danger btn-icon-sm" title="Delete" data-confirm-delete="Delete department: <?= htmlspecialchars($d['name']) ?>?">
                                        <i class="fa fa-trash"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

</main>
</div>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
