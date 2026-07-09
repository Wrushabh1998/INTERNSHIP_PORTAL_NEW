<?php
/**
 * Student — Announcement Center
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

requireStudent();
$pdo = db();
$sId = $_SESSION['student_id'];
$today = date('Y-m-d');

// Fetch current active announcements (not expired)
$anns = $pdo->prepare("
    SELECT a.*, adm.name as author_name, adm.profile_photo as author_photo
    FROM announcements a
    LEFT JOIN admin adm ON a.created_by = adm.id
    WHERE a.is_active = 1 AND (a.expires_at >= ? OR a.expires_at IS NULL)
    ORDER BY a.created_at DESC
");
$anns->execute([$today]);
$anns = $anns->fetchAll();

$pageTitle  = 'Announcements';
$userRole   = 'student';
$breadcrumb = [['label'=>'Announcements']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-student.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">Admin Announcements 📢</h1>
            <p class="page-subtitle">Stay informed about company guidelines, holidays, and events</p>
        </div>
    </div>

    <!-- Announcement list -->
    <div style="display:flex; flex-direction:column; gap:20px; max-width:800px; margin:0 auto">
        <?php if (empty($anns)): ?>
        <div class="card">
            <div class="card-body">
                <div class="empty-state">
                    <div class="empty-icon">📢</div>
                    <div class="empty-title">No announcements</div>
                    <div class="empty-msg">There are no active announcements at this time. Check back later.</div>
                </div>
            </div>
        </div>
        <?php else: foreach ($anns as $a):
            $typeColors = ['General'=>'secondary','Urgent'=>'danger','Meeting'=>'info','Holiday'=>'blue','Training'=>'success','Event'=>'purple'];
            $tc = $typeColors[$a['type']] ?? 'secondary';
        ?>
        <div class="card" style="<?= $a['type'] === 'Urgent' ? 'border-left: 5px solid var(--c-danger)' : '' ?>">
            <div class="card-body" style="padding:24px">
                <div class="d-flex justify-between align-center mb-12">
                    <div class="d-flex align-center gap-8">
                        <img src="<?= profilePhotoUrl($a['author_photo'], 'admin') ?>" class="avatar avatar-sm" alt="">
                        <div>
                            <div class="fw-600 text-sm"><?= htmlspecialchars($a['author_name'] ?? 'Super Admin') ?></div>
                            <div class="text-xs text-muted"><?= formatDate($a['created_at']) ?> (<?= timeAgo($a['created_at']) ?>)</div>
                        </div>
                    </div>
                    <span class="badge badge-<?= $tc ?>"><?= $a['type'] ?> Notice</span>
                </div>

                <h3 style="font-size:1.15rem;margin:12px 0 8px 0"><?= htmlspecialchars($a['title']) ?></h3>
                <p class="text-sm text-secondary" style="white-space:pre-line; line-height:1.6">
                    <?= htmlspecialchars($a['content']) ?>
                </p>

                <?php if ($a['attachment']): ?>
                <div style="background:var(--bg-table-head); padding:10px 16px; border-radius:var(--radius-md); display:flex; justify-between; align-center; margin-top:16px">
                    <div class="text-xs text-muted" style="display:flex; align-items:center; gap:8px">
                        <i class="fa fa-paperclip"></i>
                        <span><?= htmlspecialchars($a['attachment']) ?></span>
                    </div>
                    <a href="<?= SITE_URL ?>/uploads/announcements/<?= htmlspecialchars($a['attachment']) ?>" target="_blank" class="btn btn-secondary btn-sm" style="padding:4px 10px; font-size:0.75rem">
                        <i class="fa fa-download"></i> Download
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; endif; ?>
    </div>

</main>
</div>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
