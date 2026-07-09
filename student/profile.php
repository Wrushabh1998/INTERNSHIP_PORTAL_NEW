<?php
/**
 * Student — Profile Management
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/helpers/upload.php';

requireStudent();
$pdo = db();
$sId = $_SESSION['student_id'];

$student = getCurrentStudent();
$errors = [];

// Handle update details
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_profile') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $phone             = clean($_POST['phone'] ?? '');
        $gender            = in_array($_POST['gender']??'',['Male','Female','Other']) ? $_POST['gender'] : null;
        $emergency_contact = clean($_POST['emergency_contact'] ?? '');
        $emergency_name    = clean($_POST['emergency_name'] ?? '');
        $address           = clean($_POST['address'] ?? '');
        $city              = clean($_POST['city'] ?? '');
        $state             = clean($_POST['state'] ?? '');
        $skills            = clean($_POST['skills'] ?? '');

        // Handle profile photo upload
        $photo = $student['profile_photo'];
        if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
            $up = uploadFile($_FILES['photo'], UPLOAD_PROFILE, ALLOWED_IMAGE_TYPES, MAX_FILE_SIZE, 'avatar');
            if ($up['success']) {
                $photo = $up['filename'];
            } else {
                $errors[] = 'Photo upload error: ' . $up['error'];
            }
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("
                UPDATE students 
                SET phone=?, gender=?, emergency_contact=?, emergency_name=?, address=?, city=?, state=?, skills=?, profile_photo=?
                WHERE id=?
            ");
            $stmt->execute([$phone, $gender, $emergency_contact, $emergency_name, $address, $city, $state, $skills, $photo, $sId]);

            logActivity('student', $sId, 'update_profile', "Updated profile details");
            setFlash('success', 'Profile updated successfully.');
            redirect(SITE_URL . '/student/profile.php');
        }
    }
}

// Handle Documents upload (Resume, Aadhaar/PAN)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_docs') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        // Resume upload
        if (isset($_FILES['resume']) && $_FILES['resume']['error'] === UPLOAD_ERR_OK) {
            $up = uploadFile($_FILES['resume'], UPLOAD_DOCUMENTS, ['application/pdf'], MAX_FILE_SIZE, 'resume');
            if ($up['success']) {
                $pdo->prepare("UPDATE students SET resume_path=? WHERE id=?")->execute([$up['filename'], $sId]);
                // Insert into documents table
                $pdo->prepare("INSERT INTO documents (student_id, doc_type, title, file_path, file_size, mime_type) VALUES (?, 'Resume', 'Resume', ?, ?, ?)")
                    ->execute([$sId, $up['filename'], $up['size'], $up['mime']]);
            } else {
                $errors[] = 'Resume upload error: ' . $up['error'];
            }
        }

        // Aadhaar upload
        if (isset($_FILES['aadhaar']) && $_FILES['aadhaar']['error'] === UPLOAD_ERR_OK) {
            $up = uploadFile($_FILES['aadhaar'], UPLOAD_DOCUMENTS, ['application/pdf', 'image/jpeg', 'image/png'], MAX_FILE_SIZE, 'id_proof');
            if ($up['success']) {
                $pdo->prepare("UPDATE students SET aadhaar_path=? WHERE id=?")->execute([$up['filename'], $sId]);
                $pdo->prepare("INSERT INTO documents (student_id, doc_type, title, file_path, file_size, mime_type) VALUES (?, 'ID Card', 'Identity Proof (Aadhaar/PAN)', ?, ?, ?)")
                    ->execute([$sId, $up['filename'], $up['size'], $up['mime']]);
            } else {
                $errors[] = 'Identity Proof upload error: ' . $up['error'];
            }
        }

        if (empty($errors)) {
            logActivity('student', $sId, 'upload_profile_docs', "Uploaded profile documents");
            setFlash('success', 'Documents uploaded successfully.');
            redirect(SITE_URL . '/student/profile.php');
        }
    }
}

// Handle Password Change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'change_pw') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $currentPw = $_POST['current_pw'] ?? '';
        $newPw     = $_POST['new_pw']     ?? '';
        $confirmPw = $_POST['confirm_pw'] ?? '';

        if (!$currentPw || !$newPw || !$confirmPw) {
            $errors[] = 'All password fields are required.';
        } elseif ($newPw !== $confirmPw) {
            $errors[] = 'New password and confirmation do not match.';
        } elseif (strlen($newPw) < 6) {
            $errors[] = 'Password must be at least 6 characters.';
        } else {
            // Verify current
            $stmt = $pdo->prepare("SELECT password FROM students WHERE id=?");
            $stmt->execute([$sId]);
            $hash = $stmt->fetchColumn();

            if (password_verify($currentPw, $hash)) {
                $newHash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare("UPDATE students SET password=? WHERE id=?")->execute([$newHash, $sId]);
                logActivity('student', $sId, 'change_password', "Changed account password");
                setFlash('success', 'Password updated successfully!');
                redirect(SITE_URL . '/student/profile.php');
            } else {
                $errors[] = 'Current password is incorrect.';
            }
        }
    }
}

$pageTitle  = 'My Profile';
$userRole   = 'student';
$breadcrumb = [['label'=>'Profile']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-student.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">Profile Management ⚙️</h1>
            <p class="page-subtitle">Configure your personal, emergency details, and academic profile</p>
        </div>
    </div>

    <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <div style="display:grid;grid-template-columns: 2fr 1.2fr; gap:20px">
        <!-- Edit Profile Panel -->
        <div class="card">
            <div class="card-header"><span class="card-title"><i class="fa fa-user-edit"></i> Edit Profile Details</span></div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="update_profile">

                    <div style="display:flex;align-items:center;gap:20px;margin-bottom:20px">
                        <img src="<?= profilePhotoUrl($student['profile_photo'], 'student') ?>" class="avatar avatar-xl" style="border: 3px solid var(--primary-light)" alt="Avatar">
                        <div class="form-group" style="margin:0;flex:1">
                            <label class="form-label">Upload Profile Photo</label>
                            <input type="file" name="photo" class="form-control" accept="image/jpeg,image/png,image/webp">
                            <div class="form-text">JPG, PNG or WEBP. Max size: 2MB.</div>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Email (Read Only)</label>
                            <input type="text" class="form-control" readonly value="<?= htmlspecialchars($student['email']) ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Mobile Number</label>
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
                            <label class="form-label">Skills (Comma Separated)</label>
                            <input type="text" name="skills" class="form-control" value="<?= htmlspecialchars($student['skills'] ?? '') ?>" placeholder="PHP, Javascript, HTML…">
                        </div>
                    </div>

                    <hr class="divider">
                    <h3 class="section-title">🚨 Emergency &amp; Address Contact</h3>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">Emergency Contact Name</label>
                            <input type="text" name="emergency_name" class="form-control" value="<?= htmlspecialchars($student['emergency_name'] ?? '') ?>" placeholder="Parent / Guardian Name">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Emergency Contact Number</label>
                            <input type="tel" name="emergency_contact" class="form-control" value="<?= htmlspecialchars($student['emergency_contact'] ?? '') ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Address</label>
                        <textarea name="address" class="form-control" rows="2" placeholder="Full residential address…"><?= htmlspecialchars($student['address'] ?? '') ?></textarea>
                    </div>
                    <div class="form-row">
                        <div class="form-group">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" value="<?= htmlspecialchars($student['city'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">State</label>
                            <input type="text" name="state" class="form-control" value="<?= htmlspecialchars($student['state'] ?? '') ?>">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary mt-16"><i class="fa fa-save"></i> Save Profile</button>
                </form>
            </div>
        </div>

        <div style="display:flex; flex-direction:column; gap:20px">
            <!-- Academic Roster (Read-only) -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fa fa-graduation-cap"></i> Academic Info</span></div>
                <div class="card-body text-sm">
                    <div class="mb-12"><strong>Student ID:</strong> <code class="text-xs"><?= htmlspecialchars($student['student_id']) ?></code></div>
                    <div class="mb-12"><strong>College:</strong> <?= htmlspecialchars($student['college'] ?? 'N/A') ?></div>
                    <div class="mb-12"><strong>Department:</strong> <?= htmlspecialchars($student['dept_name'] ?? 'N/A') ?></div>
                    <div class="mb-12"><strong>Course / Roll:</strong> <?= htmlspecialchars($student['course'] ?? 'N/A') ?> / <?= htmlspecialchars($student['roll_number'] ?? 'N/A') ?></div>
                    <div class="mb-12"><strong>Assigned Mentor:</strong> <?= htmlspecialchars($student['mentor_name'] ?? 'Not Assigned') ?></div>
                    <div class="mb-0"><strong>Internship Start:</strong> <?= $student['internship_start'] ? formatDate($student['internship_start']) : 'N/A' ?></div>
                </div>
            </div>

            <!-- Documents Upload -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fa fa-paperclip"></i> Uploaded Documents</span></div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="upload_docs">

                        <div class="form-group">
                            <label class="form-label">Upload Resume (PDF Only)</label>
                            <input type="file" name="resume" class="form-control" accept="application/pdf">
                            <?php if ($student['resume_path']): ?>
                            <div class="form-text">Active: <a href="<?= SITE_URL ?>/uploads/documents/<?= htmlspecialchars($student['resume_path']) ?>" target="_blank">View Resume</a></div>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Upload ID Proof (Aadhaar/PAN)</label>
                            <input type="file" name="aadhaar" class="form-control" accept="application/pdf,image/jpeg,image/png">
                            <?php if ($student['aadhaar_path']): ?>
                            <div class="form-text">Active: <a href="<?= SITE_URL ?>/uploads/documents/<?= htmlspecialchars($student['aadhaar_path']) ?>" target="_blank">View ID Proof</a></div>
                            <?php endif; ?>
                        </div>

                        <button type="submit" class="btn btn-secondary w-100"><i class="fa fa-upload"></i> Upload Documents</button>
                    </form>
                </div>
            </div>

            <!-- Change Password -->
            <div class="card">
                <div class="card-header"><span class="card-title"><i class="fa fa-key"></i> Change Password</span></div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrfField() ?>
                        <input type="hidden" name="action" value="change_pw">
                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_pw" class="form-control" required placeholder="••••••••">
                        </div>
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_pw" class="form-control" required placeholder="••••••••">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm Password</label>
                            <input type="password" name="confirm_pw" class="form-control" required placeholder="••••••••">
                        </div>
                        <button type="submit" class="btn btn-danger w-100"><i class="fa fa-save"></i> Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

</main>
</div>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
