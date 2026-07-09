<?php
/**
 * Shared HTML Head + Page Loader
 * Include at the TOP of every page (before any output)
 */

// $pageTitle and $activeMenu must be set before including this file
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
    require_once BASE_PATH . '/config/config.php';
    require_once BASE_PATH . '/config/database.php';
    require_once BASE_PATH . '/includes/functions.php';
}
startSession();

$flash = getFlash();
$bodyClass = $bodyClass ?? '';
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="InternTrack Pro — Professional Student Internship Tracking & Attendance Management Portal">
<meta name="robots" content="noindex,nofollow">
<title><?= htmlspecialchars($pageTitle ?? 'InternTrack Pro') ?> — InternTrack Pro</title>
<link rel="icon" href="<?= SITE_URL ?>/assets/images/favicon.svg" type="image/svg+xml">

<!-- Fonts -->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">

<!-- CSS -->
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<?php if (!empty($extraCSS)): foreach ($extraCSS as $css): ?>
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/<?= $css ?>">
<?php endforeach; endif; ?>

<!-- Chart.js -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js" defer></script>

<!-- Font Awesome (icons) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

<!-- BASE_URL: injected server-side so JS always knows the correct root, regardless of host -->
<script>window.BASE_URL = '<?= SITE_URL ?>';</script>

<style>
/* Inline critical: prevent FOUC */
html[data-theme="dark"] { color-scheme: dark; }
</style>
</head>
<body class="<?= $bodyClass ?>">

<!-- Page Loader -->
<div id="page-loader" class="page-loader">
    <div>
        <div style="text-align:center">
            <div style="width:48px;height:48px;background:linear-gradient(135deg,#6366f1,#8b5cf6);border-radius:12px;display:grid;place-items:center;margin:0 auto 14px;font-size:1.5rem;">🎓</div>
            <div class="spinner" style="margin:0 auto 10px"></div>
            <div style="font-size:.875rem;color:var(--text-muted)">Loading InternTrack Pro…</div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="toast-container"></div>

<!-- Session Warning Modal -->
<div class="modal-overlay" id="session-warning-modal">
    <div class="modal" style="max-width:380px">
        <div class="modal-header">
            <span class="modal-title">⚠️ Session Expiring</span>
        </div>
        <div class="modal-body" style="text-align:center">
            <p>Your session will expire in <strong id="session-countdown">120</strong> seconds due to inactivity.</p>
            <p class="text-sm text-muted mt-8">Click below to stay logged in.</p>
        </div>
        <div class="modal-footer">
            <a href="<?= SITE_URL ?>/logout.php" class="btn btn-secondary btn-sm">Logout Now</a>
            <button id="session-extend" class="btn btn-primary btn-sm">Stay Logged In</button>
        </div>
    </div>
</div>

<!-- Confirm Dialog Modal -->
<div class="modal-overlay" id="confirm-modal">
    <div class="modal" style="max-width:400px">
        <div class="modal-header">
            <span class="modal-title">⚠️ Confirm Action</span>
            <button class="modal-close" id="confirm-cancel">✕</button>
        </div>
        <div class="modal-body">
            <p id="confirm-message">Are you sure?</p>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="confirm-cancel">Cancel</button>
            <button class="btn btn-danger" id="confirm-ok">Yes, Proceed</button>
        </div>
    </div>
</div>

<?php
// Show flash message as a toast via inline script
if ($flash): ?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    Toast.show('<?= $flash['type'] ?>', '<?= addslashes($flash['message']) ?>');
});
</script>
<?php endif; ?>
