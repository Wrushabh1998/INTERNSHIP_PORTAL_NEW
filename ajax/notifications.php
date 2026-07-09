<?php
/**
 * AJAX — Notifications Handler
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

startSession();
header('Content-Type: application/json');

if (!isStudent()) { jsonResponse(false, 'Unauthorized'); }

$pdo    = db();
$sId    = $_SESSION['student_id'];
$action = $_GET['action'] ?? $_POST['action'] ?? 'count';

if ($action === 'count') {
    $count = getUnreadNotificationCount($sId);
    jsonResponse(true, '', ['count' => $count]);
}

if ($action === 'list') {
    $limit = min(50, (int)($_GET['limit'] ?? 10));
    $stmt  = $pdo->prepare("SELECT * FROM notifications WHERE student_id=? ORDER BY created_at DESC LIMIT ?");
    $stmt->execute([$sId, $limit]);
    $items = $stmt->fetchAll();
    foreach ($items as &$n) {
        $n['time_ago'] = timeAgo($n['created_at']);
    }
    jsonResponse(true, '', ['items' => $items]);
}

if ($action === 'mark_read') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        $pdo->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND student_id=?")->execute([$id, $sId]);
    } else {
        $pdo->prepare("UPDATE notifications SET is_read=1 WHERE student_id=?")->execute([$sId]);
    }
    jsonResponse(true, 'Marked as read');
}

jsonResponse(false, 'Unknown action');
