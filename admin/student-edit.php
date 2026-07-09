<?php
/**
 * Admin — Edit Student
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';requireAdmin();
$pdo = db();

$adminUser = getCurrentAdmin();
$isMentor  = $adminUser && ($adminUser['role'] ?? 'mentor') === 'mentor';
$mentors   = $pdo->query("SELECT id, name FROM admin WHERE role = 'mentor' ORDER BY name")->fetchAll();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    setFlash('error', 'Student not specified.');
    redirect(SITE_URL . '/admin/students.php');
}

$stmt = $pdo->prepare("SELECT * FROM students WHERE id = ?");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    setFlash('error', 'Student not found.');
    redirect(SITE_URL . '/admin/students.php');
}

if ($isMentor && (int)$student['mentor_id'] !== (int)$adminUser['id']) {
    setFlash('error', 'Access denied to this student.');
    redirect(SITE_URL . '/admin/students.php');
}

$departments = $pdo->query("SELECT * FROM departments WHERE is_active=1 ORDER BY name")->fetchAll();
$batches     = $pdo->query("SELECT * FROM internship_batches WHERE is_active=1 ORDER BY start_date DESC")->fetchAll();

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $name              = clean($_POST['name'] ?? '');
        $email             = sanitizeEmail($_POST['email'] ?? '');
        $phone             = clean($_POST['phone'] ?? '');
        $gender            = in_array($_POST['gender']??'',['Male','Female','Other']) ? $_POST['gender'] : null;
        $college           = clean($_POST['college'] ?? '');
        $department_id     = (int)($_POST['department_id'] ?? 0) ?: null;
        $course            = clean($_POST['course'] ?? '');
        $roll_number       = clean($_POST['roll_number'] ?? '');
        $batch_id          = (int)($_POST['batch_id'] ?? 0) ?: null;
        
        $mentor_id = null;
        if ($isMentor) {
            $mentor_id = $adminUser['id'];
        } else {
            $mentor_id = (int)($_POST['mentor_id'] ?? 0) ?: null;
        }

        $mentor_name = '';
        if ($mentor_id) {
            $mStmt = $pdo->prepare("SELECT name FROM admin WHERE id=?");
            $mStmt->execute([$mentor_id]);
            $mentor_name = $mStmt->fetchColumn() ?: '';
        }

        $skills            = clean($_POST['skills'] ?? '');
        $internship_start  = $_POST['internship_start'] ?? null;
        $internship_end    = $_POST['internship_end'] ?? null;
        $is_active         = isset($_POST['is_active']) ? 1 : 0;
        $password          = $_POST['password'] ?? '';

        if (!$name)  $errors[] = 'Name is required.';
        if (!$email) $errors[] = 'Email is required.';
        if (!isValidEmail($email)) $errors[] = 'Valid email required.';

        // Check duplicate email
        $dup = $pdo->prepare("SELECT id FROM students WHERE email=? AND id != ?");
        $dup->execute([$email, $id]);
        if ($dup->fetchColumn()) {
            $errors[] = 'Email already in use by another student.';
        }

        if (empty($errors)) {
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
                $stmt = $pdo->prepare("
                    UPDATE students 
                    SET name=?, email=?, password=?, phone=?, gender=?, college=?, department_id=?, course=?, roll_number=?, batch_id=?, mentor_id=?, mentor_name=?, skills=?, internship_start=?, internship_end=?, is_active=?
                    WHERE id=?
                ");
                $stmt->execute([
                    $name, $email, $hash, $phone, $gender, $college, $department_id, $course, $roll_number, $batch_id, $mentor_id, $mentor_name, $skills,
                    $internship_start ?: null, $internship_end ?: null, $is_active, $id
                ]);
            } else {
                $stmt = $pdo->prepare("
                    UPDATE students 
                    SET name=?, email=?, phone=?, gender=?, college=?, department_id=?, course=?, roll_number=?, batch_id=?, mentor_id=?, mentor_name=?, skills=?, internship_start=?, internship_end=?, is_active=?
                    WHERE id=?
                ");
                $stmt->execute([
                    $name, $email, $phone, $gender, $college, $department_id, $course, $roll_number, $batch_id, $mentor_id, $mentor_name, $skills,
                    $internship_start ?: null, $internship_end ?: null, $is_active, $id
                ]);
            }

            logActivity('admin', $_SESSION['admin_id'], 'edit_student', "Updated student: $name ($id)");
            setFlash('success', "Student $name updated successfully!");
            redirect(SITE_URL . '/admin/students.php');
        }
    }
}

$pageTitle  = 'Edit Student';
$userRole   = 'admin';
$breadcrumb = [['label'=>'Students','url'=>SITE_URL.'/admin/students.php'],['label'=>'Edit Student']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-admin.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">Edit Student Details</h1>
            <p class="page-subtitle">Update information for <?= htmlspecialchars($student['name']) ?> (<?= htmlspecialchars($student['student_id']) ?>)</p>
        </div>
        <a href="<?= SITE_URL ?>/admin/students.php" class="btn btn-secondary">
            <i class="fa fa-arrow-left"></i> Back
        </a>
    </div>

    <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <form method="POST">
        <?= csrfField() ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

            <!-- Personal Info -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fa fa-user"></i> Personal Information</span></div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($student['name']) ?>">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email <span class="required">*</span></label>
                            <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($student['email']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($student['phone'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-control">
                                <option value="">Select</option>
                                <?php foreach (['Male','Female','Other'] as $g): ?>
                                <option <?= ($student['gender']??'')===$g?'selected':'' ?>><?= $g ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">New Password (leave blank to keep current)</label>
                            <input type="text" name="password" class="form-control" placeholder="Optional new password">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Skills</label>
                        <input type="text" name="skills" class="form-control" value="<?= htmlspecialchars($student['skills'] ?? '') ?>" placeholder="PHP, MySQL, JavaScript…">
                    </div>
                    <div class="form-group">
                        <label class="checkbox-label mt-12">
                            <input type="checkbox" name="is_active" <?= $student['is_active'] ? 'checked' : '' ?>>
                            Account Active
                        </label>
                    </div>
                </div>
            </div>

            <!-- Academic Info -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fa fa-graduation-cap"></i> Academic Information</span></div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">College / University</label>
                        <input type="text" name="college" class="form-control" value="<?= htmlspecialchars($student['college'] ?? '') ?>">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Department</label>
                            <select name="department_id" class="form-control">
                                <option value="">Select Dept</option>
                                <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= ($student['department_id']??0)==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Course</label>
                            <input type="text" name="course" class="form-control" value="<?= htmlspecialchars($student['course'] ?? '') ?>" placeholder="B.Tech CSE">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Roll Number</label>
                            <input type="text" name="roll_number" class="form-control" value="<?= htmlspecialchars($student['roll_number'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Batch</label>
                            <select name="batch_id" class="form-control">
                                <option value="">Select Batch</option>
                                <?php foreach ($batches as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= ($student['batch_id']??0)==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['batch_name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mentor</label>
                        <?php if ($isMentor): ?>
                            <input type="hidden" name="mentor_id" value="<?= $adminUser['id'] ?>">
                            <input type="text" class="form-control" value="<?= htmlspecialchars($adminUser['name']) ?>" readonly>
                        <?php else: ?>
                            <select name="mentor_id" class="form-control">
                                <option value="">No Mentor</option>
                                <?php foreach ($mentors as $m): ?>
                                <option value="<?= $m['id'] ?>" <?= ($student['mentor_id']??0)==$m['id']?'selected':'' ?>><?= htmlspecialchars($m['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Internship Start</label>
                            <input type="date" name="internship_start" class="form-control" value="<?= $student['internship_start'] ?? '' ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Internship End</label>
                            <input type="date" name="internship_end" class="form-control" value="<?= $student['internship_end'] ?? '' ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-between align-center mt-16">
            <a href="<?= SITE_URL ?>/admin/students.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="fa fa-save"></i> Save Changes
            </button>
        </div>
    </form>

</main>
</div>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
