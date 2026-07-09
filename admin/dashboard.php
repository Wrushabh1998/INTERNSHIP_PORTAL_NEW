<?php
/**
 * Admin Dashboard
 * InternTrack Pro
 */

define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin();

$pdo   = db();
$today = date('Y-m-d');

// ─── Dashboard Stats ──────────────────────────────────────────────────────────
$adminUser = getCurrentAdmin();
$isMentor  = $adminUser && ($adminUser['role'] ?? 'mentor') === 'mentor';
$mentorId  = $_SESSION['admin_id'];

// Total students
if ($isMentor) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE is_active=1 AND mentor_id=?");
    $stmt->execute([$mentorId]);
    $totalStudents = (int)$stmt->fetchColumn();
} else {
    $totalStudents = (int)$pdo->query("SELECT COUNT(*) FROM students WHERE is_active=1")->fetchColumn();
}

// Attendance today
if ($isMentor) {
    $attToday = $pdo->prepare("SELECT a.status, COUNT(*) as cnt FROM attendance a JOIN students s ON a.student_id=s.id WHERE a.date=? AND s.mentor_id=? GROUP BY a.status");
    $attToday->execute([$today, $mentorId]);
} else {
    $attToday = $pdo->prepare("SELECT status, COUNT(*) as cnt FROM attendance WHERE date=? GROUP BY status");
    $attToday->execute([$today]);
}
$attStats = array_column($attToday->fetchAll(), 'cnt', 'status');

$presentToday = (int)($attStats['Present'] ?? 0) + (int)($attStats['Late'] ?? 0) + (int)($attStats['Half Day'] ?? 0);
$absentToday  = $totalStudents - $presentToday - (int)($attStats['Leave'] ?? 0);
$leaveToday   = (int)($attStats['Leave'] ?? 0);
$lateToday    = (int)($attStats['Late'] ?? 0);
$halfDayToday = (int)($attStats['Half Day'] ?? 0);

// Projects
if ($isMentor) {
    $projStats = $pdo->prepare("SELECT status, COUNT(*) cnt FROM projects WHERE created_by=? OR id IN (SELECT project_id FROM project_assignments pa JOIN students s ON pa.student_id=s.id WHERE s.mentor_id=?) GROUP BY status");
    $projStats->execute([$mentorId, $mentorId]);
} else {
    $projStats = $pdo->query("SELECT status, COUNT(*) cnt FROM projects GROUP BY status");
}
$projStats = $projStats->fetchAll();
$projMap   = array_column($projStats, 'cnt', 'status');
$activeProjects    = (int)($projMap['Active'] ?? 0);
$completedProjects = (int)($projMap['Completed'] ?? 0);

// Tasks
if ($isMentor) {
    $taskStats = $pdo->prepare("SELECT t.status, COUNT(*) cnt FROM tasks t JOIN students s ON t.student_id=s.id WHERE s.mentor_id=? GROUP BY t.status");
    $taskStats->execute([$mentorId]);
} else {
    $taskStats = $pdo->query("SELECT status, COUNT(*) cnt FROM tasks GROUP BY status");
}
$taskStats = $taskStats->fetchAll();
$taskMap   = array_column($taskStats, 'cnt', 'status');
$pendingTasks   = (int)($taskMap['Pending'] ?? 0);
$completedTasks = (int)($taskMap['Completed'] ?? 0) + (int)($taskMap['Verified'] ?? 0);

// Total working hours this month
$monthStart = date('Y-m-01');
if ($isMentor) {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(a.working_hours),0) FROM attendance a JOIN students s ON a.student_id=s.id WHERE s.mentor_id=? AND a.date BETWEEN ? AND ?");
    $stmt->execute([$mentorId, $monthStart, $today]);
    $totalHours = (float)$stmt->fetchColumn();
} else {
    $totalHours = (float)$pdo->query("SELECT COALESCE(SUM(working_hours),0) FROM attendance WHERE date BETWEEN '$monthStart' AND '$today'")->fetchColumn();
}

// Pending leaves
if ($isMentor) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leave_requests lr JOIN students s ON lr.student_id=s.id WHERE lr.status='Pending' AND s.mentor_id=?");
    $stmt->execute([$mentorId]);
    $pendingLeaves = (int)$stmt->fetchColumn();
} else {
    $pendingLeaves = (int)$pdo->query("SELECT COUNT(*) FROM leave_requests WHERE status='Pending'")->fetchColumn();
}

// Pending reports
if ($isMentor) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM daily_reports dr JOIN students s ON dr.student_id=s.id WHERE dr.status='Pending' AND s.mentor_id=?");
    $stmt->execute([$mentorId]);
    $pendingReports = (int)$stmt->fetchColumn();
} else {
    $pendingReports = (int)$pdo->query("SELECT COUNT(*) FROM daily_reports WHERE status='Pending'")->fetchColumn();
}

// ─── Chart Data ───────────────────────────────────────────────────────────────
// Weekly attendance (last 7 days)
$weekLabels = [];
$weekPresent = [];
$weekAbsent  = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $weekLabels[] = date('D', strtotime($date));
    if ($isMentor) {
        $stmt = $pdo->prepare("SELECT a.status, COUNT(*) c FROM attendance a JOIN students s ON a.student_id=s.id WHERE a.date=? AND s.mentor_id=? GROUP BY a.status");
        $stmt->execute([$date, $mentorId]);
    } else {
        $stmt = $pdo->prepare("SELECT status, COUNT(*) c FROM attendance WHERE date=? GROUP BY status");
        $stmt->execute([$date]);
    }
    $wmap = array_column($stmt->fetchAll(), 'c', 'status');
    $weekPresent[] = (int)($wmap['Present'] ?? 0) + (int)($wmap['Late'] ?? 0) + (int)($wmap['Half Day'] ?? 0);
    $weekAbsent[]  = max(0, $totalStudents - $weekPresent[count($weekPresent)-1] - (int)($wmap['Leave'] ?? 0));
}

// Monthly attendance (last 6 months)
$monthLabels  = [];
$monthPresent = [];
for ($i = 5; $i >= 0; $i--) {
    $ts  = strtotime("-$i months");
    $monthLabels[]  = date('M', $ts);
    $ym  = date('Y-m', $ts);
    if ($isMentor) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM attendance a JOIN students s ON a.student_id=s.id WHERE DATE_FORMAT(a.date,'%Y-%m')=? AND a.status IN('Present','Late','Half Day') AND s.mentor_id=?");
        $stmt->execute([$ym, $mentorId]);
        $cnt = (int)$stmt->fetchColumn();
    } else {
        $cnt = (int)$pdo->query("SELECT COUNT(*) FROM attendance WHERE DATE_FORMAT(date,'%Y-%m')='$ym' AND status IN('Present','Late','Half Day')")->fetchColumn();
    }
    $monthPresent[] = $cnt;
}

// Task status for doughnut
$taskChartData = [
    'Pending'     => $taskMap['Pending']     ?? 0,
    'In Progress' => $taskMap['In Progress'] ?? 0,
    'Completed'   => $taskMap['Completed']   ?? 0,
    'Verified'    => $taskMap['Verified']    ?? 0,
];

// Department attendance today
if ($isMentor) {
    $deptAtt = $pdo->prepare("
        SELECT d.name dept, COUNT(a.id) cnt
        FROM departments d
        LEFT JOIN students s ON s.department_id=d.id AND s.is_active=1 AND s.mentor_id=?
        LEFT JOIN attendance a ON a.student_id=s.id AND a.date=? AND a.status IN('Present','Late','Half Day')
        GROUP BY d.id, d.name
        ORDER BY cnt DESC
        LIMIT 5
    ");
    $deptAtt->execute([$mentorId, $today]);
} else {
    $deptAtt = $pdo->prepare("
        SELECT d.name dept, COUNT(a.id) cnt
        FROM departments d
        LEFT JOIN students s ON s.department_id=d.id AND s.is_active=1
        LEFT JOIN attendance a ON a.student_id=s.id AND a.date=? AND a.status IN('Present','Late','Half Day')
        GROUP BY d.id, d.name
        ORDER BY cnt DESC
        LIMIT 5
    ");
    $deptAtt->execute([$today]);
}
$deptAtt = $deptAtt->fetchAll();

// Recent activity
if ($isMentor) {
    $recentAct = $pdo->prepare("
        SELECT al.*, 
               CASE al.user_type WHEN 'admin' THEN a.name ELSE s.name END as user_name
        FROM activity_logs al
        LEFT JOIN admin a ON al.user_type='admin' AND al.user_id=a.id
        LEFT JOIN students s ON al.user_type='student' AND al.user_id=s.id
        WHERE (al.user_type='admin' AND al.user_id=?) OR (al.user_type='student' AND s.mentor_id=?)
        ORDER BY al.created_at DESC LIMIT 8
    ");
    $recentAct->execute([$mentorId, $mentorId]);
} else {
    $recentAct = $pdo->query("
        SELECT al.*, 
               CASE al.user_type WHEN 'admin' THEN a.name ELSE s.name END as user_name
        FROM activity_logs al
        LEFT JOIN admin a ON al.user_type='admin' AND al.user_id=a.id
        LEFT JOIN students s ON al.user_type='student' AND al.user_id=s.id
        ORDER BY al.created_at DESC LIMIT 8
    ");
}
$recentAct = $recentAct->fetchAll();

// Top performing students (by attendance %)
if ($isMentor) {
    $topStudents = $pdo->prepare("
        SELECT s.name, s.student_id, s.profile_photo,
               COUNT(a.id) total,
               SUM(a.status IN('Present','Late','Half Day')) pres,
               ROUND(SUM(a.status IN('Present','Late','Half Day'))/NULLIF(COUNT(a.id),0)*100,1) pct
        FROM students s
        LEFT JOIN attendance a ON a.student_id=s.id
        WHERE s.is_active=1 AND s.mentor_id=?
        GROUP BY s.id
        HAVING total > 0
        ORDER BY pct DESC LIMIT 5
    ");
    $topStudents->execute([$mentorId]);
} else {
    $topStudents = $pdo->prepare("
        SELECT s.name, s.student_id, s.profile_photo,
               COUNT(a.id) total,
               SUM(a.status IN('Present','Late','Half Day')) pres,
               ROUND(SUM(a.status IN('Present','Late','Half Day'))/NULLIF(COUNT(a.id),0)*100,1) pct
        FROM students s
        LEFT JOIN attendance a ON a.student_id=s.id
        WHERE s.is_active=1
        GROUP BY s.id
        HAVING total > 0
        ORDER BY pct DESC LIMIT 5
    ");
    $topStudents->execute();
}
$topStudents = $topStudents->fetchAll();

// Recent leave requests
if ($isMentor) {
    $recentLeaves = $pdo->prepare("
        SELECT lr.*, s.name student_name, s.student_id
        FROM leave_requests lr
        JOIN students s ON lr.student_id=s.id
        WHERE s.mentor_id=?
        ORDER BY lr.created_at DESC LIMIT 5
    ");
    $recentLeaves->execute([$mentorId]);
} else {
    $recentLeaves = $pdo->prepare("
        SELECT lr.*, s.name student_name, s.student_id
        FROM leave_requests lr
        JOIN students s ON lr.student_id=s.id
        ORDER BY lr.created_at DESC LIMIT 5
    ");
    $recentLeaves->execute();
}
$recentLeaves = $recentLeaves->fetchAll();

$pageTitle   = 'Dashboard';
$userRole    = 'admin';
$breadcrumb  = [['label' => 'Dashboard']];
$extraCSS    = [];

require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-admin.php'; ?>

<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>

<main class="page-content">
    <!-- Page Header -->
    <div class="page-header">
        <div>
            <h1 class="page-title">Admin Dashboard 📊</h1>
            <p class="page-subtitle">Welcome back! Here's what's happening today — <?= date('l, d F Y') ?></p>
        </div>
        <div class="d-flex gap-8 align-center">
            <a href="<?= SITE_URL ?>/admin/reports.php" class="btn btn-secondary btn-sm">
                <i class="fa fa-file-export"></i> Export Reports
            </a>
            <a href="<?= SITE_URL ?>/admin/students.php?action=add" class="btn btn-primary btn-sm">
                <i class="fa fa-user-plus"></i> Add Student
            </a>
        </div>
    </div>

    <!-- Stat Cards Row 1 -->
    <div class="stat-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fa fa-users"></i></div>
            <div class="stat-body">
                <div class="stat-value" data-counter="<?= $totalStudents ?>"><?= $totalStudents ?></div>
                <div class="stat-label">Total Students</div>
            </div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon"><i class="fa fa-check-circle"></i></div>
            <div class="stat-body">
                <div class="stat-value" data-counter="<?= $presentToday ?>"><?= $presentToday ?></div>
                <div class="stat-label">Present Today</div>
                <div class="stat-change up">↑ <?= $totalStudents > 0 ? round($presentToday/$totalStudents*100) : 0 ?>% attendance</div>
            </div>
        </div>
        <div class="stat-card danger">
            <div class="stat-icon"><i class="fa fa-times-circle"></i></div>
            <div class="stat-body">
                <div class="stat-value" data-counter="<?= max(0,$absentToday) ?>"><?= max(0,$absentToday) ?></div>
                <div class="stat-label">Absent Today</div>
            </div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon"><i class="fa fa-calendar-times"></i></div>
            <div class="stat-body">
                <div class="stat-value" data-counter="<?= $leaveToday ?>"><?= $leaveToday ?></div>
                <div class="stat-label">On Leave</div>
                <?php if ($pendingLeaves > 0): ?>
                <div class="stat-change"><a href="<?= SITE_URL ?>/admin/leave.php" style="color:var(--c-warning)"><?= $pendingLeaves ?> pending approval</a></div>
                <?php endif; ?>
            </div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon"><i class="fa fa-clock"></i></div>
            <div class="stat-body">
                <div class="stat-value" data-counter="<?= $lateToday ?>"><?= $lateToday ?></div>
                <div class="stat-label">Late Arrivals</div>
            </div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon"><i class="fa fa-project-diagram"></i></div>
            <div class="stat-body">
                <div class="stat-value" data-counter="<?= $activeProjects ?>"><?= $activeProjects ?></div>
                <div class="stat-label">Active Projects</div>
            </div>
        </div>
        <div class="stat-card purple">
            <div class="stat-icon"><i class="fa fa-tasks"></i></div>
            <div class="stat-body">
                <div class="stat-value" data-counter="<?= $pendingTasks ?>"><?= $pendingTasks ?></div>
                <div class="stat-label">Pending Tasks</div>
            </div>
        </div>
        <div class="stat-card success">
            <div class="stat-icon"><i class="fa fa-hourglass-half"></i></div>
            <div class="stat-body">
                <div class="stat-value"><?= number_format($totalHours, 1) ?>h</div>
                <div class="stat-label">Monthly Hours</div>
            </div>
        </div>
    </div>

    <!-- Charts Row -->
    <div class="chart-grid">
        <!-- Weekly Attendance Chart -->
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fa fa-chart-bar" style="color:var(--primary)"></i> Weekly Attendance</span>
                <span class="text-sm text-muted">Last 7 days</span>
            </div>
            <div class="card-body">
                <div class="chart-container" style="height:220px">
                    <canvas id="weeklyChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Task Status Doughnut -->
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fa fa-chart-pie" style="color:var(--secondary)"></i> Task Status</span>
                <span class="text-sm text-muted"><?= $pendingTasks + $completedTasks ?> total tasks</span>
            </div>
            <div class="card-body">
                <div class="chart-container" style="height:220px">
                    <canvas id="taskChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Monthly Trend -->
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fa fa-chart-line" style="color:var(--accent-green)"></i> Monthly Trend</span>
                <span class="text-sm text-muted">Last 6 months</span>
            </div>
            <div class="card-body">
                <div class="chart-container" style="height:220px">
                    <canvas id="monthlyChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Department Attendance -->
        <div class="card">
            <div class="card-header">
                <span class="card-title"><i class="fa fa-building" style="color:var(--c-orange)"></i> Dept. Attendance Today</span>
            </div>
            <div class="card-body">
                <div class="chart-container" style="height:220px">
                    <canvas id="deptChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Tables Row -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:24px">
        <!-- Top Performers -->
        <div class="card table-card">
            <div class="card-header">
                <span class="card-title">🏆 Top Performers</span>
                <a href="<?= SITE_URL ?>/admin/performance.php" class="btn btn-ghost btn-sm">View All</a>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Student</th>
                            <th>Attendance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($topStudents)): ?>
                        <tr><td colspan="3" class="text-center text-muted" style="padding:24px">No data yet</td></tr>
                        <?php else: foreach ($topStudents as $i => $s): ?>
                        <tr>
                            <td><strong><?= $i+1 ?></strong></td>
                            <td>
                                <div class="d-flex align-center gap-8">
                                    <img src="<?= profilePhotoUrl($s['profile_photo'], 'student') ?>" class="avatar avatar-sm" alt="">
                                    <div>
                                        <div class="fw-600 text-sm"><?= htmlspecialchars($s['name']) ?></div>
                                        <div class="text-xs text-muted"><?= htmlspecialchars($s['student_id']) ?></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <div class="d-flex align-center gap-8">
                                    <div class="progress" style="flex:1;min-width:80px">
                                        <div class="progress-bar success" data-value="<?= $s['pct'] ?>" style="width:<?= $s['pct'] ?>%"></div>
                                    </div>
                                    <span class="fw-600 text-sm"><?= $s['pct'] ?>%</span>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Recent Leave Requests -->
        <div class="card table-card">
            <div class="card-header">
                <span class="card-title">📋 Leave Requests</span>
                <a href="<?= SITE_URL ?>/admin/leave.php" class="btn btn-ghost btn-sm">View All</a>
            </div>
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr><th>Student</th><th>Type</th><th>Dates</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                        <?php if (empty($recentLeaves)): ?>
                        <tr><td colspan="4" class="text-center text-muted" style="padding:24px">No leave requests</td></tr>
                        <?php else: foreach ($recentLeaves as $l): ?>
                        <tr>
                            <td>
                                <div class="fw-600 text-sm"><?= htmlspecialchars($l['student_name']) ?></div>
                                <div class="text-xs text-muted"><?= htmlspecialchars($l['student_id']) ?></div>
                            </td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($l['leave_type']) ?></span></td>
                            <td class="text-sm"><?= formatDate($l['from_date'],'d M') ?> – <?= formatDate($l['to_date'],'d M') ?></td>
                            <td><?= statusBadge($l['status']) ?></td>
                        </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Recent Activity Log -->
    <div class="card table-card">
        <div class="card-header">
            <span class="card-title">🕒 Recent Activity</span>
            <a href="<?= SITE_URL ?>/admin/audit-logs.php" class="btn btn-ghost btn-sm">View All Logs</a>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr><th>User</th><th>Role</th><th>Action</th><th>Description</th><th>IP</th><th>Time</th></tr>
                </thead>
                <tbody>
                    <?php if (empty($recentAct)): ?>
                    <tr><td colspan="6" class="text-center text-muted" style="padding:24px">No recent activity</td></tr>
                    <?php else: foreach ($recentAct as $a): ?>
                    <tr>
                        <td class="fw-600 text-sm"><?= htmlspecialchars($a['user_name'] ?? 'Unknown') ?></td>
                        <td><?= statusBadge($a['user_type'] === 'admin' ? 'Verified' : 'Present') ?></td>
                        <td><code style="font-size:.78rem;background:var(--bg-table-head);padding:2px 6px;border-radius:4px"><?= htmlspecialchars($a['action']) ?></code></td>
                        <td class="text-sm text-muted"><?= htmlspecialchars(substr($a['description'] ?? '', 0, 60)) ?></td>
                        <td class="text-xs text-muted"><?= htmlspecialchars($a['ip_address'] ?? '') ?></td>
                        <td class="text-xs text-muted"><?= timeAgo($a['created_at']) ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main><!-- /.page-content -->
</div><!-- /.main-content -->
</div><!-- /.app-layout -->

<?php
// Chart data for inline JS
$weekLabelsJson   = json_encode($weekLabels);
$weekPresentJson  = json_encode($weekPresent);
$weekAbsentJson   = json_encode($weekAbsent);
$monthLabelsJson  = json_encode($monthLabels);
$monthPresentJson = json_encode($monthPresent);
$taskKeysJson     = json_encode(array_keys($taskChartData));
$taskValsJson     = json_encode(array_values($taskChartData));
$deptLabelsJson   = json_encode(array_column($deptAtt, 'dept'));
$deptValsJson     = json_encode(array_column($deptAtt, 'cnt'));

$inlineJS = <<<JS
document.addEventListener('DOMContentLoaded', function () {
    const isDark = () => document.documentElement.getAttribute('data-theme') === 'dark';
    const gridColor = () => isDark() ? 'rgba(255,255,255,0.06)' : 'rgba(0,0,0,0.06)';
    const textColor = () => isDark() ? '#94a3b8' : '#64748b';

    Chart.defaults.font.family = "'Inter', sans-serif";

    // Weekly attendance
    new Chart(document.getElementById('weeklyChart'), {
        type: 'bar',
        data: {
            labels: $weekLabelsJson,
            datasets: [
                { label: 'Present', data: $weekPresentJson, backgroundColor: '#10b981', borderRadius: 6 },
                { label: 'Absent',  data: $weekAbsentJson,  backgroundColor: '#ef4444', borderRadius: 6 }
            ]
        },
        options: { responsive: true, maintainAspectRatio: false, 
            plugins: { legend: { position: 'top', labels: { color: textColor() } } },
            scales: { 
                x: { grid: { color: gridColor() }, ticks: { color: textColor() } }, 
                y: { grid: { color: gridColor() }, beginAtZero: true, ticks: { stepSize: 1, color: textColor() } } 
            } 
        }
    });

    // Task doughnut
    new Chart(document.getElementById('taskChart'), {
        type: 'doughnut',
        data: {
            labels: $taskKeysJson,
            datasets: [{ data: $taskValsJson, backgroundColor: ['#f59e0b','#06b6d4','#10b981','#6366f1'], borderWidth: 0, hoverOffset: 6 }]
        },
        options: { responsive: true, maintainAspectRatio: false, cutout: '65%',
            plugins: { legend: { position: 'bottom', labels: { padding: 12, boxWidth: 12, color: textColor() } } } }
    });

    // Monthly trend
    new Chart(document.getElementById('monthlyChart'), {
        type: 'line',
        data: {
            labels: $monthLabelsJson,
            datasets: [{ label: 'Attendance', data: $monthPresentJson, borderColor: '#6366f1', backgroundColor: 'rgba(99,102,241,0.1)', fill: true, tension: 0.4, pointBackgroundColor: '#6366f1', pointRadius: 5 }]
        },
        options: { responsive: true, maintainAspectRatio: false, plugins: { legend: { display: false } },
            scales: { 
                x: { grid: { color: gridColor() }, ticks: { color: textColor() } }, 
                y: { grid: { color: gridColor() }, beginAtZero: true, ticks: { color: textColor() } } 
            } 
        }
    });

    // Department bar
    new Chart(document.getElementById('deptChart'), {
        type: 'bar',
        data: {
            labels: $deptLabelsJson,
            datasets: [{ label: 'Present', data: $deptValsJson, backgroundColor: 'rgba(99,102,241,0.8)', borderRadius: 6 }]
        },
        options: { indexAxis: 'y', responsive: true, maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { 
                x: { grid: { color: gridColor() }, beginAtZero: true, ticks: { color: textColor() } }, 
                y: { grid: { color: gridColor() }, ticks: { color: textColor() } } 
            } 
        }
    });
});
JS;

require_once BASE_PATH . '/includes/footer.php';
?>
