<?php
/**
 * Admin — Add Student
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/helpers/upload.php';

requireAdmin();
$pdo = db();

$adminUser = getCurrentAdmin();
$isMentor  = $adminUser && ($adminUser['role'] ?? 'mentor') === 'mentor';
$mentors   = $pdo->query("SELECT id, name FROM admin WHERE role = 'mentor' ORDER BY name")->fetchAll();

$departments = $pdo->query("SELECT * FROM departments WHERE is_active=1 ORDER BY name")->fetchAll();
$batches     = $pdo->query("SELECT * FROM internship_batches WHERE is_active=1 ORDER BY start_date DESC")->fetchAll();

$errors = [];
$data   = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
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

        $data = [
            'name'              => clean($_POST['name'] ?? ''),
            'email'             => sanitizeEmail($_POST['email'] ?? ''),
            'phone'             => clean($_POST['phone'] ?? ''),
            'gender'            => in_array($_POST['gender']??'',['Male','Female','Other']) ? $_POST['gender'] : null,
            'college'           => clean($_POST['college'] ?? ''),
            'department_id'     => (int)($_POST['department_id'] ?? 0) ?: null,
            'course'            => clean($_POST['course'] ?? ''),
            'roll_number'       => clean($_POST['roll_number'] ?? ''),
            'batch_id'          => (int)($_POST['batch_id'] ?? 0) ?: null,
            'mentor_id'         => $mentor_id,
            'mentor_name'       => $mentor_name,
            'skills'            => clean($_POST['skills'] ?? ''),
            'internship_start'  => $_POST['internship_start'] ?? null,
            'internship_end'    => $_POST['internship_end'] ?? null,
            'password'          => $_POST['password'] ?? 'Student@123',
        ];

        // Generate student_id
        $lastId = (int)$pdo->query("SELECT MAX(id) FROM students")->fetchColumn() + 1;
        $data['student_id'] = 'INT' . date('Y') . str_pad($lastId, 3, '0', STR_PAD_LEFT);

        if (!$data['name'])  $errors[] = 'Name is required.';
        if (!$data['email']) $errors[] = 'Email is required.';
        if (!isValidEmail($data['email'])) $errors[] = 'Valid email required.';

        // Check duplicate email
        $dup = $pdo->prepare("SELECT id FROM students WHERE email=?");
        $dup->execute([$data['email']]);
        if ($dup->fetchColumn()) $errors[] = 'Email already registered.';

        if (empty($errors)) {
            $hash = password_hash($data['password'], PASSWORD_BCRYPT, ['cost' => 12]);
            $stmt = $pdo->prepare("
                INSERT INTO students (student_id,name,email,password,phone,gender,college,department_id,course,roll_number,batch_id,mentor_id,mentor_name,skills,internship_start,internship_end)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)
            ");
            $stmt->execute([
                $data['student_id'], $data['name'], $data['email'], $hash,
                $data['phone'], $data['gender'], $data['college'],
                $data['department_id'], $data['course'], $data['roll_number'],
                $data['batch_id'], $data['mentor_id'], $data['mentor_name'], $data['skills'],
                $data['internship_start'] ?: null, $data['internship_end'] ?: null
            ]);
            $newId = $pdo->lastInsertId();

            // Create welcome notification
            createNotification($newId, 'General', 'Welcome to InternTrack Pro!',
                'Your account has been created. Please complete your profile and check your assigned tasks.',
                'student/profile.php');

            logActivity('admin', $_SESSION['admin_id'], 'add_student', "Added student: {$data['name']} ({$data['student_id']})");
            setFlash('success', "Student {$data['name']} ({$data['student_id']}) added successfully! Default password: {$data['password']}");
            redirect(SITE_URL . '/admin/students.php');
        }
    }
}

$pageTitle  = 'Add Student';
$userRole   = 'admin';
$breadcrumb = [['label'=>'Students','url'=>SITE_URL.'/admin/students.php'],['label'=>'Add Student']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-admin.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">Add New Student</h1>
            <p class="page-subtitle">Create a new intern account</p>
        </div>
        <a href="<?= SITE_URL ?>/admin/students.php" class="btn btn-secondary">
            <i class="fa fa-arrow-left"></i> Back
        </a>
    </div>

    <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <form method="POST" enctype="multipart/form-data">
        <?= csrfField() ?>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px">

            <!-- Personal Info -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fa fa-user"></i> Personal Information</span></div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">Full Name <span class="required">*</span></label>
                        <input type="text" name="name" class="form-control" required value="<?= htmlspecialchars($data['name'] ?? '') ?>" placeholder="e.g. Aarav Sharma">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email <span class="required">*</span></label>
                            <input type="email" name="email" class="form-control" required value="<?= htmlspecialchars($data['email'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Phone</label>
                            <input type="tel" name="phone" class="form-control" value="<?= htmlspecialchars($data['phone'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Gender</label>
                            <select name="gender" class="form-control">
                                <option value="">Select</option>
                                <?php foreach (['Male','Female','Other'] as $g): ?>
                                <option <?= ($data['gender']??'')===$g?'selected':'' ?>><?= $g ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Password</label>
                            <input type="text" name="password" class="form-control" value="Student@123" placeholder="Default: Student@123">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Skills</label>
                        <input type="text" name="skills" class="form-control" value="<?= htmlspecialchars($data['skills'] ?? '') ?>" placeholder="PHP, MySQL, JavaScript…">
                    </div>
                </div>
            </div>

            <!-- Academic Info -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fa fa-graduation-cap"></i> Academic Information</span></div>
                <div class="card-body">
                    <div class="form-group">
                        <label class="form-label">College / University</label>
                        <input type="text" name="college" class="form-control" value="<?= htmlspecialchars($data['college'] ?? '') ?>">
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Department</label>
                            <select name="department_id" class="form-control">
                                <option value="">Select Dept</option>
                                <?php foreach ($departments as $d): ?>
                                <option value="<?= $d['id'] ?>" <?= ($data['department_id']??0)==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Course</label>
                            <input type="text" name="course" class="form-control" value="<?= htmlspecialchars($data['course'] ?? '') ?>" placeholder="B.Tech CSE">
                        </div>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Roll Number</label>
                            <input type="text" name="roll_number" class="form-control" value="<?= htmlspecialchars($data['roll_number'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Batch</label>
                            <select name="batch_id" class="form-control">
                                <option value="">Select Batch</option>
                                <?php foreach ($batches as $b): ?>
                                <option value="<?= $b['id'] ?>" <?= ($data['batch_id']??0)==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['batch_name']) ?></option>
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
                                <option value="<?= $m['id'] ?>" <?= ($data['mentor_id']??0)==$m['id']?'selected':'' ?>><?= htmlspecialchars($m['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        <?php endif; ?>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Internship Start</label>
                            <input type="date" name="internship_start" class="form-control" value="<?= $data['internship_start'] ?? '' ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Internship End</label>
                            <input type="date" name="internship_end" class="form-control" value="<?= $data['internship_end'] ?? '' ?>">
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="d-flex justify-between align-center mt-16">
            <a href="<?= SITE_URL ?>/admin/students.php" class="btn btn-secondary">Cancel</a>
            <button type="submit" class="btn btn-primary">
                <i class="fa fa-user-plus"></i> Add Student
            </button>
        </div>
    </form>

</main>
</div>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
