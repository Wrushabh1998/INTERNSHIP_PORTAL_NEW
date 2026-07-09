<?php
/**
 * Admin — Announcements
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin();
$pdo = db();

// Handle POST — create announcement
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        setFlash('error', 'Invalid token.'); redirect(SITE_URL . '/admin/announcements.php');
    }
    $title   = clean($_POST['title'] ?? '');
    $content = htmlspecialchars_decode(clean($_POST['content'] ?? ''));
    $type    = in_array($_POST['type']??'',['General','Urgent','Meeting','Holiday','Training','Event']) ? $_POST['type'] : 'General';
    $expires = $_POST['expires_at'] ?? null;

    if (!$title || !$content) {
        setFlash('error', 'Title and content are required.');
        redirect(SITE_URL . '/admin/announcements.php');
    }

    // File upload
    $attachment = null;
    if (!empty($_FILES['attachment']['name'])) {
        $ext  = strtolower(pathinfo($_FILES['attachment']['name'], PATHINFO_EXTENSION));
        $name = uniqid('ann_') . '.' . $ext;
        $dest = UPLOAD_ANNOUNCEMENTS . '/' . $name;
        if (move_uploaded_file($_FILES['attachment']['tmp_name'], $dest)) {
            $attachment = $name;
        }
    }

    $pdo->prepare("INSERT INTO announcements (title,content,type,attachment,expires_at,created_by) VALUES (?,?,?,?,?,?)")
        ->execute([$title, $content, $type, $attachment, $expires ?: null, $_SESSION['admin_id']]);

    // Notify all active students
    $students = $pdo->query("SELECT id FROM students WHERE is_active=1")->fetchAll(PDO::FETCH_COLUMN);
    foreach ($students as $sid) {
        createNotification($sid, 'Announcement', "[$type] $title", substr($content, 0, 120), 'student/announcements.php');
    }

    logActivity('admin', $_SESSION['admin_id'], 'create_announcement', "Created: $title");
    setFlash('success', 'Announcement published and students notified.');
    redirect(SITE_URL . '/admin/announcements.php');
}

// Delete
if (isset($_GET['action']) && $_GET['action']==='delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $pdo->prepare("DELETE FROM announcements WHERE id=?")->execute([$id]);
    setFlash('success', 'Announcement deleted.');
    redirect(SITE_URL . '/admin/announcements.php');
}

// List
$page  = max(1, (int)($_GET['page'] ?? 1));
$total = (int)$pdo->query("SELECT COUNT(*) FROM announcements")->fetchColumn();
$pag   = paginate($total, $page);
$anns  = $pdo->query("SELECT a.*, adm.name admin_name FROM announcements a LEFT JOIN admin adm ON a.created_by=adm.id ORDER BY a.created_at DESC LIMIT {$pag['per_page']} OFFSET {$pag['offset']}")->fetchAll();

$pageTitle  = 'Announcements';
$userRole   = 'admin';
$breadcrumb = [['label'=>'Announcements']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-admin.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <h1 class="page-title">Announcements</h1>
        <button class="btn btn-primary" data-modal-open="ann-modal">
            <i class="fa fa-bullhorn"></i> New Announcement
        </button>
    </div>

    <div class="card table-card">
        <div class="card-header"><span class="card-title">All Announcements</span></div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>#</th><th>Title</th><th>Type</th><th>Attachment</th><th>Posted By</th><th>Expires</th><th>Date</th><th>Actions</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($anns)): ?>
                    <tr><td colspan="8"><div class="empty-state"><div class="empty-icon">📢</div><div class="empty-title">No announcements yet</div></div></td></tr>
                    <?php else: foreach ($anns as $i => $a): ?>
                    <tr>
                        <td><?= $pag['offset']+$i+1 ?></td>
                        <td>
                            <div class="fw-600"><?= htmlspecialchars($a['title']) ?></div>
                            <div class="text-xs text-muted"><?= htmlspecialchars(substr($a['content'],0,80)) ?>…</div>
                        </td>
                        <td>
                            <?php
                            $typeColors = ['General'=>'secondary','Urgent'=>'danger','Meeting'=>'info','Holiday'=>'blue','Training'=>'success','Event'=>'purple'];
                            $tc = $typeColors[$a['type']] ?? 'secondary';
                            ?>
                            <span class="badge badge-<?= $tc ?>"><?= $a['type'] ?></span>
                        </td>
                        <td>
                            <?php if ($a['attachment']): ?>
                            <a href="<?= SITE_URL ?>/uploads/announcements/<?= htmlspecialchars($a['attachment']) ?>" target="_blank" class="btn btn-ghost btn-sm">
                                <i class="fa fa-download"></i>
                            </a>
                            <?php else: ?><span class="text-muted text-xs">—</span><?php endif; ?>
                        </td>
                        <td class="text-sm"><?= htmlspecialchars($a['admin_name'] ?? '—') ?></td>
                        <td class="text-sm text-muted"><?= $a['expires_at'] ? formatDate($a['expires_at']) : 'Never' ?></td>
                        <td class="text-sm text-muted"><?= formatDateTime($a['created_at']) ?></td>
                        <td>
                            <a href="?action=delete&id=<?= $a['id'] ?>"
                               class="btn btn-danger btn-icon-sm"
                               data-confirm-delete="Delete this announcement?">
                                <i class="fa fa-trash"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer"><?= renderPagination($pag, SITE_URL . '/admin/announcements.php') ?></div>
    </div>

</main>
</div>
</div>

<!-- New Announcement Modal -->
<div class="modal-overlay" id="ann-modal">
    <div class="modal" style="max-width:620px">
        <div class="modal-header">
            <span class="modal-title">📢 New Announcement</span>
            <button class="modal-close">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Title <span class="required">*</span></label>
                    <input type="text" name="title" class="form-control" required placeholder="Announcement title">
                </div>
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Type</label>
                        <select name="type" class="form-control">
                            <?php foreach (['General','Urgent','Meeting','Holiday','Training','Event'] as $t): ?>
                            <option><?= $t ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Expires On</label>
                        <input type="date" name="expires_at" class="form-control" min="<?= date('Y-m-d') ?>">
                    </div>
                </div>
                <div class="form-group">
                    <label class="form-label">Content <span class="required">*</span></label>
                    <textarea name="content" class="form-control" rows="5" required placeholder="Announcement content…"></textarea>
                </div>
                <div class="form-group">
                    <label class="form-label">Attachment (optional)</label>
                    <input type="file" name="attachment" class="form-control" accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.png">
                    <div class="form-text">Max 5MB. PDF, DOC, PPT, Images allowed.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-bullhorn"></i> Publish</button>
            </div>
        </form>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
