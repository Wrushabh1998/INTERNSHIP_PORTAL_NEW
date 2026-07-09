<?php
/**
 * Admin Sidebar Navigation
 * Included inside .app-layout
 */
$currentPage = basename($_SERVER['PHP_SELF'], '.php');
$adminUser   = getCurrentAdmin();

function navLink(string $file, string $icon, string $label, string $current, string $badge = ''): void {
    $base    = SITE_URL . '/admin/' . $file;
    $name    = basename($file, '.php');
    $active  = ($name === $current || str_starts_with($current, $name)) ? 'active' : '';
    $bdg     = $badge ? '<span class="nav-badge">' . $badge . '</span>' : '';
    echo '<li class="nav-item"><a href="' . $base . '" class="nav-link ' . $active . '">'
       . '<span class="nav-icon"><i class="fa ' . $icon . '"></i></span>'
       . '<span class="nav-label">' . $label . '</span>' . $bdg . '</a></li>';
}

// Pending leave count for badge
$pendingLeaves = 0;
try {
    $stmt = db()->query("SELECT COUNT(*) FROM leave_requests WHERE status='Pending'");
    $pendingLeaves = (int)$stmt->fetchColumn();
} catch (Exception $e) {}
?>
<aside class="sidebar" id="sidebar">
    <!-- Brand -->
    <div class="sidebar-brand">
        <div class="brand-icon">🎓</div>
        <div class="brand-text">InternTrack <span style="color:var(--primary-light)">Pro</span></div>
    </div>

    <!-- Navigation -->
    <nav class="sidebar-nav">
        <div class="nav-section-label">Overview</div>
        <ul>
            <?php navLink('dashboard.php', 'fa-tachometer-alt', 'Dashboard', $currentPage); ?>
        </ul>

        <div class="nav-section-label">Management</div>
        <ul>
            <?php navLink('students.php',     'fa-users',        'Students',     $currentPage); ?>
            <?php navLink('attendance.php',   'fa-calendar-check','Attendance',  $currentPage); ?>
            <?php navLink('leave.php',        'fa-calendar-times','Leave',       $currentPage, $pendingLeaves > 0 ? $pendingLeaves : ''); ?>
            <?php navLink('projects.php',     'fa-project-diagram','Projects',   $currentPage); ?>
            <?php navLink('tasks.php',        'fa-tasks',        'Tasks',        $currentPage); ?>
            <?php navLink('daily-reports.php','fa-file-alt',     'Daily Reports',$currentPage); ?>
        </ul>

        <div class="nav-section-label">Communication</div>
        <ul>
            <?php navLink('announcements.php','fa-bullhorn',     'Announcements',$currentPage); ?>
            <?php navLink('certificates.php', 'fa-certificate',  'Certificates', $currentPage); ?>
        </ul>

        <?php $isMentor = ($adminUser['role'] ?? 'mentor') === 'mentor'; ?>
        <div class="nav-section-label">Analytics</div>
        <ul>
            <?php navLink('performance.php',  'fa-chart-bar',    'Performance',  $currentPage); ?>
            <?php navLink('reports.php',      'fa-file-export',  'Reports',      $currentPage); ?>
            <?php if (!$isMentor): ?>
            <?php navLink('audit-logs.php',   'fa-shield-alt',   'Audit Logs',   $currentPage); ?>
            <?php endif; ?>
        </ul>

        <?php if (!$isMentor): ?>
        <div class="nav-section-label">System</div>
        <ul>
            <?php navLink('settings.php',     'fa-cog',          'Settings',     $currentPage); ?>
        </ul>
        <?php endif; ?>
    </nav>

    <!-- Footer / User -->
    <div class="sidebar-footer">
        <div class="sidebar-user" data-dropdown="admin-profile-dd">
            <img src="<?= profilePhotoUrl($adminUser['profile_photo'] ?? null, 'admin') ?>" alt="Admin">
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($adminUser['name'] ?? 'Admin') ?></div>
                <div class="user-role"><?= $isMentor ? 'Mentor' : 'Administrator' ?></div>
            </div>
        </div>
    </div>
</aside>
<div class="sidebar-backdrop" id="sidebar-backdrop"></div>
