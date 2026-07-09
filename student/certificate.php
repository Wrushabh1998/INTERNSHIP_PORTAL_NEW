<?php
/**
 * Student — Internship Certificate
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

requireStudent();
$pdo = db();
$sId = $_SESSION['student_id'];

// Check if certificate issued
$stmt = $pdo->prepare("
    SELECT c.*, adm.name as issuer_name
    FROM certificates c
    LEFT JOIN admin adm ON c.issued_by = adm.id
    WHERE c.student_id = ?
    ORDER BY c.created_at DESC
");
$stmt->execute([$sId]);
$certs = $stmt->fetchAll();

$pageTitle  = 'Certificate';
$userRole   = 'student';
$breadcrumb = [['label'=>'Certificate']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-student.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">Internship Certificate</h1>
            <p class="page-subtitle">Download your completion certificate and experience letter</p>
        </div>
    </div>

    <!-- Certificate Card -->
    <div style="max-width: 600px; margin: 0 auto">
        <?php if (empty($certs)): ?>
        <div class="card" style="border-top: 4px solid var(--c-warning)">
            <div class="card-body text-center" style="padding: 40px 20px">
                <div style="font-size: 3rem; margin-bottom:12px">🎓</div>
                <h3 style="font-size:1.2rem;margin-bottom:8px">In Progress</h3>
                <p class="text-sm text-secondary">
                    Your certificate will be generated and issued by the administration once you have completed your required hours, task allocations, and the internship duration has ended.
                </p>
                <div class="alert alert-info mt-16" style="margin-top:20px; text-align:left">
                    ℹ️ <strong>Requirements:</strong> Make sure your daily reports are verified and task progress is logged daily to avoid approval delays.
                </div>
            </div>
        </div>
        <?php else: foreach ($certs as $c): ?>
        <div class="card mb-20" style="border-top: 4px solid var(--c-success)">
            <div class="card-body text-center" style="padding: 32px 24px">
                <div style="font-size: 3rem; margin-bottom:12px">📜</div>
                <h3 style="font-size:1.25rem;margin:0"><?= htmlspecialchars($c['cert_type']) ?> Certificate Issued</h3>
                <p class="text-xs text-muted mt-8">Certificate Number: <code><?= htmlspecialchars($c['cert_number']) ?></code></p>
                
                <hr class="divider" style="margin:20px 0">

                <div class="d-flex justify-between align-center text-sm mb-16 text-secondary">
                    <span><strong>Issued Date:</strong> <?= formatDate($c['issued_date']) ?></span>
                    <span><strong>Issued By:</strong> <?= htmlspecialchars($c['issuer_name'] ?? 'Administration') ?></span>
                </div>

                <a href="<?= SITE_URL ?>/admin/view-certificate.php?id=<?= $c['id'] ?>" target="_blank" class="btn btn-primary w-100">
                    <i class="fa fa-print"></i> Print / Download PDF
                </a>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>

</main>
</div>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
