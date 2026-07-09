<?php
/**
 * Admin — Certificate Issuance & Generation
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin();
$pdo = db();

$errors = [];

// Issue Certificate
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'issue') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $studentId = (int)($_POST['student_id'] ?? 0);
        $certType  = in_array($_POST['cert_type']??'',['Completion','Experience','Appreciation']) ? $_POST['cert_type'] : 'Completion';
        $issueDate = $_POST['issued_date'] ?? date('Y-m-d');

        if (!$studentId) {
            $errors[] = 'Please select a student.';
        }

        // Check if already issued of this type
        $chk = $pdo->prepare("SELECT id FROM certificates WHERE student_id=? AND cert_type=?");
        $chk->execute([$studentId, $certType]);
        if ($chk->fetchColumn()) {
            $errors[] = "A $certType certificate has already been issued to this student.";
        }

        if (empty($errors)) {
            $certNumber = generateCertNumber($studentId);
            $stmt = $pdo->prepare("
                INSERT INTO certificates (student_id, cert_type, issued_date, cert_number, issued_by)
                VALUES (?, ?, ?, ?, ?)
            ");
            $stmt->execute([$studentId, $certType, $issueDate ?: null, $certNumber, $_SESSION['admin_id']]);
            logActivity('admin', $_SESSION['admin_id'], 'issue_certificate', "Issued $certType certificate to student ID: $studentId ($certNumber)");
            setFlash('success', 'Certificate issued successfully!');
            redirect(SITE_URL . '/admin/certificates.php');
        }
    }
}

// Fetch issued certificates
$adminUser = getCurrentAdmin();
$isMentor  = $adminUser && ($adminUser['role'] ?? 'mentor') === 'mentor';
$mentorId  = $_SESSION['admin_id'];

if ($isMentor) {
    $certs = $pdo->prepare("
        SELECT c.*, s.name as student_name, s.student_id as sid, d.name as dept_name, adm.name as issuer_name
        FROM certificates c
        JOIN students s ON c.student_id = s.id
        LEFT JOIN departments d ON s.department_id = d.id
        LEFT JOIN admin adm ON c.issued_by = adm.id
        WHERE s.mentor_id = ?
        ORDER BY c.created_at DESC
    ");
    $certs->execute([$mentorId]);
    $certs = $certs->fetchAll();

    $stmt_studs = $pdo->prepare("SELECT id, name, student_id FROM students WHERE is_active=1 AND mentor_id=? ORDER BY name");
    $stmt_studs->execute([$mentorId]);
    $students = $stmt_studs->fetchAll();
} else {
    $certs = $pdo->query("
        SELECT c.*, s.name as student_name, s.student_id as sid, d.name as dept_name, adm.name as issuer_name
        FROM certificates c
        JOIN students s ON c.student_id = s.id
        LEFT JOIN departments d ON s.department_id = d.id
        LEFT JOIN admin adm ON c.issued_by = adm.id
        ORDER BY c.created_at DESC
    ")->fetchAll();

    $students = $pdo->query("SELECT id, name, student_id FROM students WHERE is_active=1 ORDER BY name")->fetchAll();
}

$pageTitle  = 'Certificates';
$userRole   = 'admin';
$breadcrumb = [['label'=>'Certificates']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-admin.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">Certificate Generation</h1>
            <p class="page-subtitle">Generate and download completion/experience letters for interns</p>
        </div>
    </div>

    <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <div style="display:grid;grid-template-columns:1.2fr 2fr;gap:20px">
        <!-- Issue Panel -->
        <div class="card">
            <div class="card-header"><span class="card-title"><i class="fa fa-stamp"></i> Issue Certificate</span></div>
            <div class="card-body">
                <form method="POST">
                    <?= csrfField() ?>
                    <input type="hidden" name="action" value="issue">

                    <div class="form-group">
                        <label class="form-label">Select Student <span class="required">*</span></label>
                        <select name="student_id" class="form-control" required>
                            <option value="">— Select Intern —</option>
                            <?php foreach ($students as $s): ?>
                            <option value="<?= $s['id'] ?>"><?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['student_id']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Certificate Type <span class="required">*</span></label>
                        <select name="cert_type" class="form-control" required>
                            <option value="Completion">Internship Completion Certificate</option>
                            <option value="Experience">Experience Letter</option>
                            <option value="Appreciation">Certificate of Appreciation</option>
                        </select>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Date of Issue</label>
                        <input type="date" name="issued_date" class="form-control" value="<?= date('Y-m-d') ?>">
                    </div>

                    <button type="submit" class="btn btn-primary w-100 mt-16">
                        <i class="fa fa-certificate"></i> Generate &amp; Issue
                    </button>
                </form>
            </div>
        </div>

        <!-- Issued List -->
        <div class="card table-card">
            <div class="card-header"><span class="card-title">Issued Certificates</span></div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Student</th>
                            <th>Certificate Type</th>
                            <th>Certificate ID</th>
                            <th>Issue Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($certs)): ?>
                        <tr><td colspan="5"><div class="empty-state"><div class="empty-icon">📜</div><div class="empty-title">No certificates issued yet</div></div></td></tr>
                        <?php else: foreach ($certs as $c): ?>
                        <tr>
                            <td>
                                <div class="fw-600 text-sm"><?= htmlspecialchars($c['student_name']) ?></div>
                                <div class="text-xs text-muted"><?= htmlspecialchars($c['sid']) ?></div>
                            </td>
                            <td><span class="badge badge-purple"><?= $c['cert_type'] ?></span></td>
                            <td><code class="text-xs"><?= htmlspecialchars($c['cert_number']) ?></code></td>
                            <td class="text-sm"><?= formatDate($c['issued_date']) ?></td>
                            <td>
                                <a href="<?= SITE_URL ?>/admin/view-certificate.php?id=<?= $c['id'] ?>" target="_blank" class="btn btn-ghost btn-sm" title="Print/Download PDF">
                                    <i class="fa fa-print"></i> Print
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</main>
</div>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
