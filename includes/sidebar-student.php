<?php
/**
 * Student Sidebar Navigation
 */
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$student     = getCurrentStudent();
$unreadCount = getUnreadNotificationCount($student['id'] ?? 0);

// Fetch today's punch status for sidebar quick-punch buttons
$_sidebarToday   = date('Y-m-d');
$_sidebarAtt     = false;
try {
    $pdo = db();
    $_sidebarStmt = $pdo->prepare("SELECT punch_in, lunch_start, lunch_end, punch_out FROM attendance WHERE student_id=? AND date=?");
    $_sidebarStmt->execute([$student['id'] ?? 0, $_sidebarToday]);
    $_sidebarAtt = $_sidebarStmt->fetch();
} catch (Exception $e) {}

$_piDone  = !empty($_sidebarAtt['punch_in']);
$_lsDone  = !empty($_sidebarAtt['lunch_start']);
$_leDone  = !empty($_sidebarAtt['lunch_end']);
$_poDone  = !empty($_sidebarAtt['punch_out']);
$_csrfTok = generateCsrfToken();

function sNavLink(string $file, string $icon, string $label, string $current, string $badge = ''): void {
    $base   = SITE_URL . '/student/' . $file;
    $name   = basename($file, '.php');
    $active = ($name === $current) ? 'active' : '';
    $bdg    = $badge ? '<span class="nav-badge">' . $badge . '</span>' : '';
    echo '<li class="nav-item"><a href="' . $base . '" class="nav-link ' . $active . '">'
       . '<span class="nav-icon"><i class="fa ' . $icon . '"></i></span>'
       . '<span class="nav-label">' . $label . '</span>' . $bdg . '</a></li>';
}
?>
<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="brand-icon">🎓</div>
        <div class="brand-text">InternTrack <span style="color:var(--primary-light)">Pro</span></div>
    </div>

    <nav class="sidebar-nav">
        <div class="nav-section-label">Overview</div>
        <ul>
            <?php sNavLink('dashboard.php',     'fa-home',          'Dashboard',        $currentPage); ?>
        </ul>

        <div class="nav-section-label">Attendance</div>
        <ul>
            <?php sNavLink('attendance.php',    'fa-fingerprint',   'Mark Attendance',  $currentPage); ?>
            <?php sNavLink('leave.php',         'fa-calendar-times','Leave',            $currentPage); ?>
        </ul>

        <?php if (!$_poDone): ?>
        <!-- Quick Punch Buttons in sidebar -->
        <div class="nav-section-label">Quick Punch</div>
        <div class="sidebar-punch-panel">
            <input type="hidden" id="sb-csrf" value="<?= htmlspecialchars($_csrfTok) ?>">

            <?php if (!$_piDone): ?>
            <button id="sb-btn-punch-in" class="sb-punch-btn sb-punch-in" title="Punch In">
                <i class="fa fa-sign-in-alt"></i> <span>Punch In</span>
            </button>
            <?php else: ?>
            <div class="sb-punch-done">
                <i class="fa fa-check-circle" style="color:var(--c-success)"></i>
                <span>In: <?= date('h:i A', strtotime($_sidebarAtt['punch_in'])) ?></span>
            </div>
            <?php endif; ?>

            <?php if ($_piDone && !$_poDone): ?>
                <?php if (!$_lsDone): ?>
                <button id="sb-btn-lunch-start" class="sb-punch-btn sb-lunch" title="Start Lunch">
                    <i class="fa fa-utensils"></i> <span>Lunch Start</span>
                </button>
                <?php elseif (!$_leDone): ?>
                <button id="sb-btn-lunch-end" class="sb-punch-btn sb-lunch" title="End Lunch">
                    <i class="fa fa-utensils"></i> <span>Lunch End</span>
                </button>
                <?php else: ?>
                <div class="sb-punch-done">
                    <i class="fa fa-coffee" style="color:var(--c-warning)"></i>
                    <span>Lunch: Done</span>
                </div>
                <?php endif; ?>

                <button id="sb-btn-punch-out" class="sb-punch-btn sb-punch-out" title="Punch Out">
                    <i class="fa fa-sign-out-alt"></i> <span>Punch Out</span>
                </button>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div class="nav-section-label">Today</div>
        <div class="sidebar-punch-panel">
            <div class="sb-punch-done sb-punch-complete">
                <i class="fa fa-check-circle" style="color:var(--c-success)"></i>
                <span>Attendance Complete</span>
            </div>
        </div>
        <?php endif; ?>

        <div class="nav-section-label">Work</div>
        <ul>
            <?php sNavLink('tasks.php',         'fa-tasks',         'My Tasks',         $currentPage); ?>
            <?php sNavLink('projects.php',      'fa-project-diagram','Projects',        $currentPage); ?>
            <?php sNavLink('daily-report.php',  'fa-file-alt',      'Daily Report',     $currentPage); ?>
        </ul>

        <div class="nav-section-label">Resources</div>
        <ul>
            <?php sNavLink('documents.php',     'fa-folder-open',   'Documents',        $currentPage); ?>
            <?php sNavLink('announcements.php', 'fa-bullhorn',      'Announcements',    $currentPage); ?>
            <?php sNavLink('notifications.php', 'fa-bell',          'Notifications',    $currentPage, $unreadCount > 0 ? $unreadCount : ''); ?>
        </ul>

        <div class="nav-section-label">Profile</div>
        <ul>
            <?php sNavLink('performance.php',   'fa-chart-line',    'Performance',      $currentPage); ?>
            <?php sNavLink('feedback.php',      'fa-comment-dots',  'Feedback',         $currentPage); ?>
            <?php sNavLink('certificate.php',   'fa-certificate',   'Certificate',      $currentPage); ?>
            <?php sNavLink('profile.php',       'fa-user-cog',      'My Profile',       $currentPage); ?>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <div class="sidebar-user" data-dropdown="student-profile-dd">
            <img src="<?= profilePhotoUrl($student['profile_photo'] ?? null, 'student') ?>" alt="Student">
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($student['name'] ?? 'Student') ?></div>
                <div class="user-role"><?= htmlspecialchars($student['student_id'] ?? 'Intern') ?></div>
            </div>
        </div>
    </div>
</aside>
<div class="sidebar-backdrop" id="sidebar-backdrop"></div>

<style>
/* ── Sidebar Quick Punch Panel ─────────────────────────────────── */
.sidebar-punch-panel {
    padding: 0 14px 10px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.sb-punch-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    width: 100%;
    padding: 9px 14px;
    border: none;
    border-radius: 8px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.18s ease;
    letter-spacing: 0.3px;
}
.sb-punch-btn:hover { filter: brightness(1.08); transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,.15); }
.sb-punch-btn:disabled { opacity: 0.55; cursor: not-allowed; transform: none; }
.sb-punch-in  { background: linear-gradient(135deg, #22c55e, #16a34a); color: #fff; }
.sb-punch-out { background: linear-gradient(135deg, #ef4444, #dc2626); color: #fff; }
.sb-lunch     { background: linear-gradient(135deg, #f59e0b, #d97706); color: #fff; }
.sb-punch-btn .spinner { width:14px;height:14px;border-radius:50%;border:2px solid rgba(255,255,255,.3);border-top-color:#fff;animation:spin .6s linear infinite;display:inline-block; }
.sb-punch-done {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.78rem;
    color: var(--text-muted);
    padding: 6px 4px;
    font-weight: 500;
}
.sb-punch-complete span { color: var(--c-success); font-weight: 600; }
</style>

<script>
(function () {
    'use strict';
    const AJAX_URL = (window.BASE_URL || '') + '/ajax/attendance.php';

    function sbPunch(type) {
        const idMap = {
            punch_in: 'sb-btn-punch-in',
            lunch_start: 'sb-btn-lunch-start',
            lunch_end: 'sb-btn-lunch-end',
            punch_out: 'sb-btn-punch-out'
        };
        const btn = document.getElementById(idMap[type]);
        if (!btn) return;
        const origHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span>';

        const csrfToken = document.getElementById('sb-csrf')?.value || '';
        const fd = new FormData();
        fd.append('action', type);
        fd.append('image', '');
        fd.append('_csrf_token', csrfToken);
        fd.append('user_agent', navigator.userAgent);

        function doPost() {
            fetch(AJAX_URL, { method: 'POST', body: fd })
                .then(r => r.json())
                .then(res => {
                    if (res.success) {
                        if (typeof Toast !== 'undefined') {
                            const labels = { punch_in: 'Punched In ✅', punch_out: 'Punched Out 🔴', lunch_start: 'Lunch Started 🍽️', lunch_end: 'Lunch Ended ✅' };
                            Toast.show('success', labels[type] || 'Done', res.message);
                        }
                        setTimeout(() => location.reload(), 1200);
                    } else {
                        if (typeof Toast !== 'undefined') Toast.show('error', 'Failed', res.message);
                        btn.disabled = false;
                        btn.innerHTML = origHTML;
                    }
                })
                .catch(() => {
                    if (typeof Toast !== 'undefined') Toast.show('error', 'Network Error', 'Could not connect to server.');
                    btn.disabled = false;
                    btn.innerHTML = origHTML;
                });
        }

        // Capture GPS only for punch_in and punch_out
        if ((type === 'punch_in' || type === 'punch_out') && navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(
                pos => {
                    fd.append('lat', pos.coords.latitude.toFixed(6));
                    fd.append('lng', pos.coords.longitude.toFixed(6));
                    doPost();
                },
                () => doPost(),
                { enableHighAccuracy: true, timeout: 8000 }
            );
        } else {
            doPost();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        ['punch_in','lunch_start','lunch_end','punch_out'].forEach(type => {
            const idMap = { punch_in:'sb-btn-punch-in', lunch_start:'sb-btn-lunch-start', lunch_end:'sb-btn-lunch-end', punch_out:'sb-btn-punch-out' };
            const btn = document.getElementById(idMap[type]);
            if (btn) btn.addEventListener('click', () => sbPunch(type));
        });
    });
})();
</script>
