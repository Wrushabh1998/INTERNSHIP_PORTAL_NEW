<?php
/**
 * Student — Notification Center
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

requireStudent();
$pdo = db();
$sId = $_SESSION['student_id'];

// Handle Mark All Read
if (isset($_GET['action']) && $_GET['action'] === 'read_all') {
    $pdo->prepare("UPDATE notifications SET is_read=1 WHERE student_id=?")->execute([$sId]);
    logActivity('student', $sId, 'notifications_read_all', "Marked all notifications as read");
    setFlash('success', 'All notifications marked as read.');
    redirect(SITE_URL . '/student/notifications.php');
}

// Handle Delete Notification
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pdo->prepare("DELETE FROM notifications WHERE id=? AND student_id=?")->execute([$id, $sId]);
    redirect(SITE_URL . '/student/notifications.php');
}

// Fetch all notifications
$notifications = $pdo->prepare("
    SELECT * FROM notifications 
    WHERE student_id=? 
    ORDER BY created_at DESC
");
$notifications->execute([$sId]);
$notifications = $notifications->fetchAll();

$pageTitle  = 'Notifications';
$userRole   = 'student';
$breadcrumb = [['label'=>'Notifications']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-student.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">Notification Center</h1>
            <p class="page-subtitle">Stay updated on task updates, leaves status, and announcements</p>
        </div>
        <?php if (!empty($notifications)): ?>
        <a href="?action=read_all" class="btn btn-secondary btn-sm">
            <i class="fa fa-envelope-open"></i> Mark All as Read
        </a>
        <?php endif; ?>
    </div>

    <!-- Notifications List -->
    <div class="card" style="max-width: 720px; margin: 0 auto">
        <div class="card-body" style="padding: 0">
            <?php if (empty($notifications)): ?>
            <div class="empty-state" style="padding: 60px 20px">
                <div class="empty-icon">🔔</div>
                <div class="empty-title">All caught up!</div>
                <div class="empty-msg">You have no notifications. Keep checking here for updates.</div>
            </div>
            <?php else: foreach ($notifications as $n): 
                $types = ['Attendance'=>'fingerprint', 'Leave'=>'calendar-times', 'Task'=>'tasks', 'Announcement'=>'bullhorn', 'Deadline'=>'clock', 'General'=>'info-circle'];
                $icon = $types[$n['type']] ?? 'info-circle';
            ?>
            <div class="notif-item <?= $n['is_read'] ? '' : 'unread' ?>" style="display:flex; align-items:center; justify-between; padding: 18px 24px; border-bottom:1px solid var(--border)">
                <div style="display:flex; align-items:center; gap: 16px; flex:1">
                    <span class="notif-dot" style="<?= $n['is_read'] ? 'visibility:hidden' : '' ?>"></span>
                    <div style="width: 36px; height: 36px; border-radius:50%; background:var(--bg-table-head); display:grid; place-items:center" class="text-sm">
                        <i class="fa fa-<?= $icon ?>"></i>
                    </div>
                    <div>
                        <div class="fw-600 text-sm" style="color:var(--text-primary)"><?= htmlspecialchars($n['title']) ?></div>
                        <div class="text-xs text-muted" style="margin:4px 0"><?= htmlspecialchars($n['message']) ?></div>
                        <div class="text-xs text-muted" style="font-size:0.7rem"><?= timeAgo($n['created_at']) ?></div>
                    </div>
                </div>
                
                <div class="d-flex gap-8">
                    <?php if ($n['link']): ?>
                    <a href="<?= SITE_URL ?>/<?= htmlspecialchars($n['link']) ?>" class="btn btn-ghost btn-sm btn-icon" title="View details">
                        <i class="fa fa-chevron-right"></i>
                    </a>
                    <?php endif; ?>
                    <a href="?action=delete&id=<?= $n['id'] ?>" class="btn btn-ghost btn-sm btn-icon" title="Delete notification" onclick="return confirm('Delete this notification?')">
                        <i class="fa fa-trash"></i>
                    </a>
                </div>
            </div>
            <?php endforeach; endif; ?>
        </div>
    </div>

</main>
</div>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
