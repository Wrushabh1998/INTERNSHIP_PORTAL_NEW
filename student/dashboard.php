<?php
/**
 * Student — Dashboard
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

requireStudent();
$pdo    = db();
$sId    = $_SESSION['student_id'];
$today  = date('Y-m-d');
$student = getCurrentStudent();

// Today's attendance
$todayAtt = $pdo->prepare("SELECT * FROM attendance WHERE student_id=? AND date=?");
$todayAtt->execute([$sId, $today]);
$todayAtt = $todayAtt->fetch();
if (!$todayAtt) {
    $todayAtt = [
        'status' => 'Not Marked',
        'punch_in' => null,
        'lunch_start' => null,
        'lunch_end' => null,
        'punch_out' => null
    ];
}

// Attendance stats
$totalDays  = (int)$pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id=?")->execute([$sId]) ? 0 : 0;
$cntStmt    = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id=?");
$cntStmt->execute([$sId]);
$totalDays  = (int)$cntStmt->fetchColumn();

$preStmt = $pdo->prepare("SELECT COUNT(*) FROM attendance WHERE student_id=? AND status IN('Present','Late','Half Day')");
$preStmt->execute([$sId]);
$presentDays = (int)$preStmt->fetchColumn();
$attPct = $totalDays > 0 ? round($presentDays / $totalDays * 100, 1) : 0;

// Monthly working hours
$monthHrsStmt = $pdo->prepare("SELECT COALESCE(SUM(working_hours),0) FROM attendance WHERE student_id=? AND DATE_FORMAT(date,'%Y-%m')=?");
$monthHrsStmt->execute([$sId, date('Y-m')]);
$monthHrs = (float)$monthHrsStmt->fetchColumn();

// Task stats
$taskStmt = $pdo->prepare("SELECT status, COUNT(*) c FROM tasks WHERE student_id=? GROUP BY status");
$taskStmt->execute([$sId]);
$taskMap  = array_column($taskStmt->fetchAll(), 'c', 'status');
$pendingTasks   = (int)($taskMap['Pending'] ?? 0);
$inProgTasks    = (int)($taskMap['In Progress'] ?? 0);
$completedTasks = (int)($taskMap['Completed'] ?? 0) + (int)($taskMap['Verified'] ?? 0);
$totalTasks     = array_sum($taskMap);
$taskPct = $totalTasks > 0 ? round($completedTasks / $totalTasks * 100) : 0;

// Assigned projects
$projStmt = $pdo->prepare("SELECT p.* FROM projects p JOIN project_assignments pa ON p.id=pa.project_id WHERE pa.student_id=? AND p.status='Active'");
$projStmt->execute([$sId]);
$projects = $projStmt->fetchAll();

// Pending reports
$pendingRep = (int)$pdo->prepare("SELECT COUNT(*) FROM daily_reports WHERE student_id=? AND status='Pending'")->execute([$sId]);
$prStmt = $pdo->prepare("SELECT COUNT(*) FROM daily_reports WHERE student_id=? AND status='Pending'");
$prStmt->execute([$sId]);
$pendingRep = (int)$prStmt->fetchColumn();

// Recent announcements
$annStmt = $pdo->query("SELECT * FROM announcements WHERE is_active=1 ORDER BY created_at DESC LIMIT 3");
$anns    = $annStmt->fetchAll();

// Recent notifications
$notifStmt = $pdo->prepare("SELECT * FROM notifications WHERE student_id=? ORDER BY created_at DESC LIMIT 5");
$notifStmt->execute([$sId]);
$notifs = $notifStmt->fetchAll();

// Upcoming task deadlines
$deadlineStmt = $pdo->prepare("SELECT * FROM tasks WHERE student_id=? AND deadline >= ? AND status NOT IN('Completed','Verified') ORDER BY deadline ASC LIMIT 5");
$deadlineStmt->execute([$sId, $today]);
$deadlines = $deadlineStmt->fetchAll();

// Weekly attendance for chart (last 7 days)
$weeklyAtt = [];
$weekLabels = [];
for ($i = 6; $i >= 0; $i--) {
    $d = date('Y-m-d', strtotime("-$i days"));
    $weekLabels[] = date('D', strtotime($d));
    $ws = $pdo->prepare("SELECT working_hours FROM attendance WHERE student_id=? AND date=?");
    $ws->execute([$sId, $d]);
    $weeklyAtt[] = (float)($ws->fetchColumn() ?: 0);
}

// Internship progress
$internStart  = $student['internship_start'] ? strtotime($student['internship_start']) : time();
$internEnd    = $student['internship_end']   ? strtotime($student['internship_end'])   : time();
$totalInternDays  = max(1, ($internEnd - $internStart) / 86400);
$elapsed          = max(0, (time() - $internStart) / 86400);
$internPct        = min(100, round($elapsed / $totalInternDays * 100));

$pageTitle  = 'My Dashboard';
$userRole   = 'student';
$breadcrumb = [['label'=>'Dashboard']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-student.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <!-- Greeting -->
    <div class="page-header">
        <div>
            <h1 class="page-title">
                <?php
                $hr = (int)date('H');
                echo $hr < 12 ? '🌅' : ($hr < 17 ? '☀️' : '🌙');
                echo ' Good ' . ($hr < 12 ? 'Morning' : ($hr < 17 ? 'Afternoon' : 'Evening')) . ', ' . htmlspecialchars(explode(' ', $student['name'])[0]) . '!';
                ?>
            </h1>
            <p class="page-subtitle"><?= date('l, d F Y') ?> — <?= htmlspecialchars($student['student_id']) ?> | <strong>Mentor:</strong> <?= htmlspecialchars($student['mentor_name'] ?? 'Not Assigned') ?></p>
        </div>
        <a href="<?= SITE_URL ?>/student/attendance.php" class="btn btn-primary">
            <i class="fa fa-fingerprint"></i> Mark Attendance
        </a>
    </div>

    <!-- Today's Punch Status -->
    <div class="card" style="margin-bottom:20px;background:linear-gradient(135deg,rgba(99,102,241,.08),rgba(139,92,246,.08));border-color:rgba(99,102,241,.2)">
        <div class="card-body">
            <div class="d-flex align-center justify-between flex-wrap gap-12">
                <div>
                    <h3 class="section-title mb-0">📋 Today's Attendance</h3>
                    <p class="text-sm text-muted mt-8">
                        Status: <?= statusBadge($todayAtt['status'] ?? 'Not Marked') ?>
                    </p>
                </div>
                <div class="punch-status-bar">
                    <div class="punch-step <?= $todayAtt['punch_in'] ? 'done' : '' ?>">
                        <i class="fa fa-sign-in-alt"></i>
                        <div>
                            <div style="font-size:.7rem;opacity:.7">PUNCH IN</div>
                            <div><?= $todayAtt['punch_in'] ? formatTime($todayAtt['punch_in']) : 'Pending' ?></div>
                        </div>
                    </div>
                    <div class="punch-step <?= $todayAtt['lunch_start'] ? 'done' : '' ?>">
                        <i class="fa fa-utensils"></i>
                        <div>
                            <div style="font-size:.7rem;opacity:.7">LUNCH</div>
                            <div><?= $todayAtt['lunch_start'] ? formatTime($todayAtt['lunch_start']) : 'Pending' ?></div>
                        </div>
                    </div>
                    <div class="punch-step <?= $todayAtt['lunch_end'] ? 'done' : '' ?>">
                        <i class="fa fa-utensils" style="transform:rotate(180deg)"></i>
                        <div>
                            <div style="font-size:.7rem;opacity:.7">RESUME</div>
                            <div><?= $todayAtt['lunch_end'] ? formatTime($todayAtt['lunch_end']) : 'Pending' ?></div>
                        </div>
                    </div>
                    <div class="punch-step <?= $todayAtt['punch_out'] ? 'done' : '' ?>">
                        <i class="fa fa-sign-out-alt"></i>
                        <div>
                            <div style="font-size:.7rem;opacity:.7">PUNCH OUT</div>
                            <div><?= $todayAtt['punch_out'] ? formatTime($todayAtt['punch_out']) : 'Pending' ?></div>
                        </div>
                    </div>
                </div>
                <div style="text-align:right">
                    <div style="font-size:2rem;font-weight:800;font-family:'Outfit',sans-serif">
                        <?= number_format($todayAtt['working_hours'] ?? 0, 2) ?>h
                    </div>
                    <div class="text-sm text-muted">Working Hours Today</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stat Cards -->
    <div class="stat-grid">
        <div class="stat-card success">
            <div class="stat-icon"><i class="fa fa-calendar-check"></i></div>
            <div class="stat-body">
                <div class="stat-value"><?= $attPct ?>%</div>
                <div class="stat-label">Attendance</div>
                <div class="progress mt-8"><div class="progress-bar success" data-value="<?= $attPct ?>" style="width:<?= $attPct ?>%"></div></div>
            </div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon"><i class="fa fa-hourglass-half"></i></div>
            <div class="stat-body">
                <div class="stat-value"><?= number_format($monthHrs,1) ?>h</div>
                <div class="stat-label">Hours This Month</div>
            </div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon"><i class="fa fa-tasks"></i></div>
            <div class="stat-body">
                <div class="stat-value"><?= $pendingTasks + $inProgTasks ?></div>
                <div class="stat-label">Active Tasks</div>
            </div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon"><i class="fa fa-check-double"></i></div>
            <div class="stat-body">
                <div class="stat-value"><?= $completedTasks ?></div>
                <div class="stat-label">Completed Tasks</div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fa fa-project-diagram"></i></div>
            <div class="stat-body">
                <div class="stat-value"><?= count($projects) ?></div>
                <div class="stat-label">Active Projects</div>
            </div>
        </div>
        <div class="stat-card purple">
            <div class="stat-icon"><i class="fa fa-chart-line"></i></div>
            <div class="stat-body">
                <div class="stat-value"><?= $internPct ?>%</div>
                <div class="stat-label">Internship Progress</div>
                <div class="progress mt-8"><div class="progress-bar" data-value="<?= $internPct ?>" style="width:<?= $internPct ?>%"></div></div>
            </div>
        </div>
    </div>

    <!-- Charts + Content Row -->
    <div style="display:grid;grid-template-columns:2fr 1fr;gap:20px;margin-bottom:20px">
        <!-- Weekly hours chart -->
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fa fa-chart-bar" style="color:var(--primary)"></i> Weekly Working Hours</span>
            </div>
            <div class="card-body">
                <div class="chart-container" style="height:200px">
                    <canvas id="weeklyHoursChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Task completion doughnut -->
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fa fa-tasks" style="color:var(--secondary)"></i> Task Progress</span>
            </div>
            <div class="card-body">
                <div class="chart-container" style="height:200px">
                    <canvas id="taskDonutChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Bottom Row -->
    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:20px">

        <!-- Upcoming Deadlines -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">⏰ Upcoming Deadlines</span>
                <a href="<?= SITE_URL ?>/student/tasks.php" class="btn btn-ghost btn-sm">View All</a>
            </div>
            <div class="card-body" style="padding:14px">
                <?php if (empty($deadlines)): ?>
                <div class="empty-state" style="padding:24px"><div class="empty-icon">🎉</div><div class="empty-title">No upcoming deadlines!</div></div>
                <?php else: foreach ($deadlines as $t):
                    $daysLeft = (int)((strtotime($t['deadline']) - strtotime($today)) / 86400);
                    $urgClass = $daysLeft <= 2 ? 'danger' : ($daysLeft <= 5 ? 'warning' : 'success');
                ?>
                <div style="padding:10px 0;border-bottom:1px solid var(--border)">
                    <div class="d-flex justify-between align-center">
                        <div class="fw-600 text-sm"><?= htmlspecialchars(substr($t['title'],0,30)) ?>…</div>
                        <span class="badge badge-<?= $urgClass ?>"><?= $daysLeft ?>d left</span>
                    </div>
                    <div class="progress mt-8" style="height:4px">
                        <div class="progress-bar <?= $urgClass ?>" data-value="<?= $t['progress'] ?>" style="width:<?= $t['progress'] ?>%"></div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Recent Notifications -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">🔔 Notifications</span>
                <a href="<?= SITE_URL ?>/student/notifications.php" class="btn btn-ghost btn-sm">View All</a>
            </div>
            <div class="card-body" style="padding:0">
                <?php if (empty($notifs)): ?>
                <div class="empty-state" style="padding:24px"><div class="empty-icon">🔕</div><div class="empty-title">No notifications</div></div>
                <?php else: foreach ($notifs as $n): ?>
                <div class="notif-item <?= $n['is_read']?'':'unread' ?>">
                    <span class="notif-dot" style="<?= $n['is_read']?'visibility:hidden':'' ?>"></span>
                    <div class="notif-body">
                        <div class="notif-title"><?= htmlspecialchars($n['title']) ?></div>
                        <div class="notif-msg"><?= htmlspecialchars(substr($n['message'],0,60)) ?></div>
                        <div class="notif-time"><?= timeAgo($n['created_at']) ?></div>
                    </div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>

        <!-- Announcements -->
        <div class="card">
            <div class="card-header">
                <span class="card-title">📢 Announcements</span>
                <a href="<?= SITE_URL ?>/student/announcements.php" class="btn btn-ghost btn-sm">View All</a>
            </div>
            <div class="card-body" style="padding:14px">
                <?php if (empty($anns)): ?>
                <div class="empty-state" style="padding:24px"><div class="empty-icon">📭</div><div class="empty-title">No announcements</div></div>
                <?php else: foreach ($anns as $a):
                    $typeColors = ['General'=>'secondary','Urgent'=>'danger','Meeting'=>'info','Holiday'=>'blue','Training'=>'success','Event'=>'purple'];
                    $tc = $typeColors[$a['type']] ?? 'secondary';
                ?>
                <div style="padding:10px 0;border-bottom:1px solid var(--border)">
                    <div class="d-flex align-center gap-6 mb-0">
                        <span class="badge badge-<?= $tc ?>" style="font-size:.65rem"><?= $a['type'] ?></span>
                        <span class="text-xs text-muted"><?= timeAgo($a['created_at']) ?></span>
                    </div>
                    <div class="fw-600 text-sm mt-8"><?= htmlspecialchars($a['title']) ?></div>
                    <div class="text-xs text-muted"><?= htmlspecialchars(substr($a['content'],0,80)) ?>…</div>
                </div>
                <?php endforeach; endif; ?>
            </div>
        </div>
    </div>

</main>
</div>
</div>

<?php
$weekLabelsJson = json_encode($weekLabels);
$weeklyAttJson  = json_encode($weeklyAtt);
$taskLabelsJson = json_encode(['Pending','In Progress','Completed','Verified']);
$taskValsJson   = json_encode([
    $taskMap['Pending'] ?? 0,
    $taskMap['In Progress'] ?? 0,
    $taskMap['Completed'] ?? 0,
    $taskMap['Verified'] ?? 0,
]);
$inlineJS = <<<JS
document.addEventListener('DOMContentLoaded', function () {
    Chart.defaults.font.family = "'Inter', sans-serif";
    new Chart(document.getElementById('weeklyHoursChart'), {
        type: 'bar',
        data: {
            labels: $weekLabelsJson,
            datasets: [{ label: 'Working Hours', data: $weeklyAttJson, backgroundColor: 'rgba(99,102,241,0.75)', borderRadius: 6 }]
        },
        options: { responsive:true, maintainAspectRatio:false, plugins:{legend:{display:false}},
            scales:{ y:{ beginAtZero:true, max:10, ticks:{stepSize:2} } } }
    });
    new Chart(document.getElementById('taskDonutChart'), {
        type: 'doughnut',
        data: {
            labels: $taskLabelsJson,
            datasets: [{ data: $taskValsJson, backgroundColor:['#f59e0b','#06b6d4','#10b981','#6366f1'], borderWidth:0 }]
        },
        options: { responsive:true, maintainAspectRatio:false, cutout:'65%', plugins:{legend:{position:'bottom',labels:{padding:8,boxWidth:10}}} }
    });
});
JS;

require_once BASE_PATH . '/includes/footer.php';
?>
