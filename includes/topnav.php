<?php
/**
 * Top Navigation Bar (shared admin & student)
 * Variables required: $pageTitle, $breadcrumb (array), $userRole ('admin'|'student')
 */
$userRole    = $userRole ?? 'student';
$currentUser = ($userRole === 'admin') ? getCurrentAdmin() : getCurrentStudent();
$unread      = ($userRole === 'student') ? getUnreadNotificationCount($currentUser['id'] ?? 0) : 0;
$profileUrl  = ($userRole === 'admin') ? SITE_URL . '/admin/profile.php' : SITE_URL . '/student/profile.php';
?>
<header class="topnav">
    <!-- Sidebar toggle -->
    <button class="topnav-toggle" data-sidebar-toggle aria-label="Toggle sidebar">
        <i class="fa fa-bars"></i>
    </button>

    <!-- Breadcrumb -->
    <nav class="breadcrumb" aria-label="Breadcrumb">
        <a href="<?= SITE_URL ?>/<?= $userRole ?>/dashboard.php">
            <i class="fa fa-home"></i>
        </a>
        <?php if (!empty($breadcrumb)): foreach ($breadcrumb as $bc): ?>
        <span class="bc-sep">›</span>
        <?php if ($bc['url'] ?? false): ?>
        <a href="<?= $bc['url'] ?>"><?= htmlspecialchars($bc['label']) ?></a>
        <?php else: ?>
        <span class="bc-current"><?= htmlspecialchars($bc['label']) ?></span>
        <?php endif; ?>
        <?php endforeach; endif; ?>
    </nav>

    <!-- Actions -->
    <div class="topnav-actions">
        <!-- Dark mode toggle -->
        <button class="theme-toggle" data-theme-toggle title="Toggle dark mode" aria-label="Toggle dark mode">
            <span class="theme-icon">🌙</span>
        </button>

        <!-- Notification bell (student only) -->
        <?php if ($userRole === 'student'): ?>
        <div class="position-relative" style="position:relative">
            <button class="topnav-btn" data-dropdown="notif-panel" title="Notifications" id="notif-btn">
                <i class="fa fa-bell"></i>
                <span id="notif-count" class="badge-count" style="<?= $unread > 0 ? '' : 'display:none' ?>">
                    <?= $unread > 0 ? ($unread > 99 ? '99+' : $unread) : '' ?>
                </span>
            </button>
            <!-- Notification Panel -->
            <div class="notif-panel" id="notif-panel">
                <div class="notif-panel-header">
                    <span class="fw-600" style="font-size:.875rem">Notifications</span>
                    <a href="<?= SITE_URL ?>/student/notifications.php" class="text-sm" style="color:var(--primary)">View All</a>
                </div>
                <div class="notif-list" id="notif-list">
                    <div style="padding:24px;text-align:center;color:var(--text-muted);font-size:.875rem">
                        <span class="spinner spinner-sm" style="margin:0 auto 8px;display:block"></span>
                        Loading…
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Profile dropdown -->
        <div class="profile-dropdown">
            <img src="<?= profilePhotoUrl($currentUser['profile_photo'] ?? null, $userRole) ?>"
                 alt="Profile" class="topnav-avatar"
                 data-dropdown="profile-menu" id="profile-avatar">
            <div class="dropdown-menu" id="profile-menu">
                <div class="dropdown-user-info">
                    <div class="d-name"><?= htmlspecialchars($currentUser['name'] ?? '') ?></div>
                    <div class="d-email"><?= htmlspecialchars($currentUser['email'] ?? '') ?></div>
                </div>
                <a href="<?= $profileUrl ?>" class="dropdown-item">
                    <i class="fa fa-user"></i> My Profile
                </a>
                <?php if ($userRole === 'admin'): ?>
                <a href="<?= SITE_URL ?>/admin/settings.php" class="dropdown-item">
                    <i class="fa fa-cog"></i> Settings
                </a>
                <?php endif; ?>
                <div class="dropdown-divider"></div>
                <a href="<?= SITE_URL ?>/logout.php" class="dropdown-item danger">
                    <i class="fa fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </div>
</header>

<?php if ($userRole === 'student'): ?>
<script>
// Load notifications in panel on open
document.getElementById('notif-btn')?.addEventListener('click', function () {
    fetch((window.BASE_URL || '') + '/ajax/notifications.php?action=list&limit=8')
        .then(r => r.json())
        .then(data => {
            const list = document.getElementById('notif-list');
            if (!list) return;
            if (!data.items || !data.items.length) {
                list.innerHTML = '<div class="empty-state" style="padding:32px 16px"><div class="empty-icon">🔔</div><div class="empty-title">No notifications</div></div>';
                return;
            }
            list.innerHTML = data.items.map(n => `
                <div class="notif-item ${n.is_read == 0 ? 'unread' : ''}" onclick="window.location='${n.link || ((window.BASE_URL || '') + '/student/notifications.php')}'" >
                    <span class="notif-dot" style="${n.is_read != 0 ? 'visibility:hidden' : ''}"></span>
                    <div class="notif-body">
                        <div class="notif-title">${n.title}</div>
                        <div class="notif-msg">${n.message}</div>
                        <div class="notif-time">${n.time_ago}</div>
                    </div>
                </div>
            `).join('');
        });
});
</script>
<?php endif; ?>
