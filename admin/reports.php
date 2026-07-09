<?php
/**
 * Admin — Reports & Analytics
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin();
$pdo = db();

$type       = clean($_GET['type'] ?? 'attendance'); // attendance, leaves, tasks, performance
$studentId  = (int)($_GET['student'] ?? 0);
$deptId     = (int)($_GET['dept'] ?? 0);
$batchId    = (int)($_GET['batch'] ?? 0);
$startDate  = $_GET['start_date'] ?? date('Y-m-01');
$endDate    = $_GET['end_date'] ?? date('Y-m-d');
$exportCsv  = isset($_GET['export']) && $_GET['export'] === 'csv';

$where = ['1=1'];
$params = [];

// Apply general filters depending on joins
if ($studentId) { $where[] = 's.id = ?'; $params[] = $studentId; }
if ($deptId)    { $where[] = 's.department_id = ?'; $params[] = $deptId; }
if ($batchId)   { $where[] = 's.batch_id = ?'; $params[] = $batchId; }

$adminUser = getCurrentAdmin();
$isMentor  = $adminUser && ($adminUser['role'] ?? 'mentor') === 'mentor';
$mentorId  = $_SESSION['admin_id'];

if ($isMentor) {
    $where[] = 's.mentor_id = ?';
    $params[] = $mentorId;
}

$data = [];
$headers = [];

if ($type === 'attendance') {
    $where[] = 'a.date BETWEEN ? AND ?';
    $params[] = $startDate;
    $params[] = $endDate;
    $whereStr = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT a.date, s.name as student_name, s.student_id as sid, d.name as dept_name,
               a.punch_in, a.punch_out, a.working_hours, a.status, a.remarks,
               a.punch_in_lat, a.punch_in_lng, a.punch_out_lat, a.punch_out_lng
        FROM attendance a
        JOIN students s ON a.student_id = s.id
        LEFT JOIN departments d ON s.department_id = d.id
        WHERE $whereStr
        ORDER BY a.date DESC, s.name ASC
    ");
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    $headers = ['Date', 'Student Name', 'Student ID', 'Department', 'Punch In', 'Punch Out', 'Hours', 'Status', 'Remarks'];

    if ($exportCsv) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="attendance_report.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($data as $r) {
            fputcsv($out, [$r['date'], $r['student_name'], $r['sid'], $r['dept_name'], $r['punch_in'], $r['punch_out'], $r['working_hours'], $r['status'], $r['remarks']]);
        }
        fclose($out);
        exit;
    }
}

elseif ($type === 'leaves') {
    $where[] = 'lr.from_date BETWEEN ? AND ?';
    $params[] = $startDate;
    $params[] = $endDate;
    $whereStr = implode(' AND ', $where);

    $stmt = $pdo->prepare("
        SELECT s.name as student_name, s.student_id as sid, d.name as dept_name,
               lr.leave_type, lr.from_date, lr.to_date, lr.days, lr.status, lr.admin_remark
        FROM leave_requests lr
        JOIN students s ON lr.student_id = s.id
        LEFT JOIN departments d ON s.department_id = d.id
        WHERE $whereStr
        ORDER BY lr.created_at DESC
    ");
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    $headers = ['Student Name', 'Student ID', 'Department', 'Type', 'From', 'To', 'Days', 'Status', 'Feedback'];

    if ($exportCsv) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="leave_report.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($data as $r) {
            fputcsv($out, [$r['student_name'], $r['sid'], $r['dept_name'], $r['leave_type'], $r['from_date'], $r['to_date'], $r['days'], $r['status'], $r['admin_remark']]);
        }
        fclose($out);
        exit;
    }
}

elseif ($type === 'tasks') {
    $whereStr = implode(' AND ', $where);
    $stmt = $pdo->prepare("
        SELECT s.name as student_name, s.student_id as sid, t.title, p.title as project_title,
               t.priority, t.deadline, t.progress, t.status
        FROM tasks t
        JOIN students s ON t.student_id = s.id
        LEFT JOIN projects p ON t.project_id = p.id
        WHERE $whereStr
        ORDER BY t.created_at DESC
    ");
    $stmt->execute($params);
    $data = $stmt->fetchAll();
    $headers = ['Student Name', 'Student ID', 'Task Title', 'Project', 'Priority', 'Deadline', 'Progress %', 'Status'];

    if ($exportCsv) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="tasks_report.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($data as $r) {
            fputcsv($out, [$r['student_name'], $r['sid'], $r['title'], $r['project_title'], $r['priority'], $r['deadline'], $r['progress'], $r['status']]);
        }
        fclose($out);
        exit;
    }
}

elseif ($type === 'performance') {
    $whereStr = implode(' AND ', $where);
    $stmt = $pdo->prepare("
        SELECT s.id, s.name as student_name, s.student_id as sid, d.name as dept_name
        FROM students s
        LEFT JOIN departments d ON s.department_id = d.id
        WHERE $whereStr AND s.is_active = 1
        ORDER BY s.name ASC
    ");
    $stmt->execute($params);
    $studs = $stmt->fetchAll();

    foreach ($studs as $student) {
        $sId = $student['id'];
        $attPct = getAttendancePercentage($sId);
        $taskPct = getTaskCompletionPercentage($sId);

        // Avg hours
        $hrsStmt = $pdo->prepare("SELECT AVG(working_hours) FROM attendance WHERE student_id=? AND status IN('Present','Late','Half Day')");
        $hrsStmt->execute([$sId]);
        $avgHrs = round((float)($hrsStmt->fetchColumn() ?: 0.0), 1);

        // Reports Approved vs Total
        $repStmt = $pdo->prepare("SELECT COUNT(*) total, SUM(status='Approved') approved FROM daily_reports WHERE student_id=?");
        $repStmt->execute([$sId]);
        $repStats = $repStmt->fetch();
        $repPct = $repStats['total'] > 0 ? round(($repStats['approved'] / $repStats['total']) * 100, 1) : 0.0;

        $overallScore = round(($attPct * 0.3) + ($taskPct * 0.3) + ($repPct * 0.2) + (min(100.0, ($avgHrs/8)*100) * 0.2), 1);

        $data[] = [
            'student_name' => $student['student_name'],
            'sid' => $student['sid'],
            'dept_name' => $student['dept_name'] ?? 'N/A',
            'attendance_pct' => $attPct . '%',
            'avg_hours' => $avgHrs . 'h',
            'tasks_pct' => $taskPct . '%',
            'reports_pct' => $repPct . '%',
            'overall_score' => $overallScore . '%'
        ];
    }
    $headers = ['Student Name', 'Student ID', 'Department', 'Attendance Rate', 'Avg Daily Hours', 'Task Completion', 'Report Quality', 'Performance Score'];

    if ($exportCsv) {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="performance_report.csv"');
        $out = fopen('php://output', 'w');
        fputcsv($out, $headers);
        foreach ($data as $r) {
            fputcsv($out, [$r['student_name'], $r['sid'], $r['dept_name'], $r['attendance_pct'], $r['avg_hours'], $r['tasks_pct'], $r['reports_pct'], $r['overall_score']]);
        }
        fclose($out);
        exit;
    }
}

// Fetch filter dependencies
$students    = $pdo->query("SELECT id, name, student_id FROM students WHERE is_active=1 ORDER BY name")->fetchAll();
$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();
$batches     = $pdo->query("SELECT * FROM internship_batches ORDER BY start_date DESC")->fetchAll();

$pageTitle  = 'Reports & Exports';
$userRole   = 'admin';
$breadcrumb = [['label'=>'Reports']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-admin.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">Reports &amp; Exports</h1>
            <p class="page-subtitle">Compile metrics and generate printable worksheets or CSV files</p>
        </div>
    </div>

    <!-- Filters Dashboard -->
    <div class="card" style="margin-bottom:24px">
        <div class="card-header"><span class="card-title"><i class="fa fa-filter"></i> Report Configuration</span></div>
        <div class="card-body">
            <form method="GET">
                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Report Template</label>
                        <select name="type" class="form-control" onchange="toggleDateFilters(this.value)">
                            <option value="attendance" <?= $type==='attendance'?'selected':'' ?>>Attendance &amp; Hours Log</option>
                            <option value="leaves" <?= $type==='leaves'?'selected':'' ?>>Leave Analytics</option>
                            <option value="tasks" <?= $type==='tasks'?'selected':'' ?>>Task Allocations &amp; Deadlines</option>
                            <option value="performance" <?= $type==='performance'?'selected':'' ?>>Performance Score Roster</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Student Filter</label>
                        <select name="student" class="form-control">
                            <option value="">All Students</option>
                            <?php foreach ($students as $s): ?>
                            <option value="<?= $s['id'] ?>" <?= $studentId==$s['id']?'selected':'' ?>><?= htmlspecialchars($s['name']) ?> (<?= htmlspecialchars($s['student_id']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Department Filter</label>
                        <select name="dept" class="form-control">
                            <option value="">All Departments</option>
                            <?php foreach ($departments as $d): ?>
                            <option value="<?= $d['id'] ?>" <?= $deptId==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Batch Filter</label>
                        <select name="batch" class="form-control">
                            <option value="">All Batches</option>
                            <?php foreach ($batches as $b): ?>
                            <option value="<?= $b['id'] ?>" <?= $batchId==$b['id']?'selected':'' ?>><?= htmlspecialchars($b['batch_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-row" id="date-filters" style="<?= ($type==='tasks'||$type==='performance')?'display:none':'' ?>">
                    <div class="form-group">
                        <label class="form-label">Start Date</label>
                        <input type="date" name="start_date" class="form-control" value="<?= htmlspecialchars($startDate) ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date</label>
                        <input type="date" name="end_date" class="form-control" value="<?= htmlspecialchars($endDate) ?>">
                    </div>
                </div>

                <div class="d-flex gap-8 align-center mt-12">
                    <button type="submit" class="btn btn-primary"><i class="fa fa-sync-alt"></i> Compile Report</button>
                    <a href="?<?= http_build_query(array_merge($_GET, ['export'=>'csv'])) ?>" class="btn btn-success"><i class="fa fa-file-csv"></i> Download CSV</a>
                    <button type="button" onclick="window.print()" class="btn btn-secondary"><i class="fa fa-print"></i> Print Report</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Data Sheet -->
    <div class="card table-card">
        <div class="card-header">
            <span class="card-title">compiled datasheet</span>
            <span class="text-sm text-muted"><?= count($data) ?> rows compiled</span>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <?php foreach ($headers as $head): ?>
                        <th><?= htmlspecialchars($head) ?></th>
                        <?php endforeach; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data)): ?>
                    <tr><td colspan="<?= count($headers) ?: 1 ?>"><div class="empty-state"><div class="empty-icon">📊</div><div class="empty-title">No matching records found</div></div></td></tr>
                    <?php else: foreach ($data as $row): ?>
                    <tr>
                        <?php if ($type === 'attendance'): ?>
                        <td><?= formatDate($row['date']) ?></td>
                        <td><strong><?= htmlspecialchars($row['student_name']) ?></strong></td>
                        <td><code class="text-xs"><?= htmlspecialchars($row['sid']) ?></code></td>
                        <td class="text-xs text-muted"><?= htmlspecialchars($row['dept_name'] ?? 'General') ?></td>
                        <td class="text-sm">
                            <?= $row['punch_in'] ? formatTime($row['punch_in']) : '—' ?>
                            <?php if (!empty($row['punch_in_lat']) && !empty($row['punch_in_lng'])): ?>
                                <div style="margin-top:2px">
                                    <a href="https://www.google.com/maps?q=<?= $row['punch_in_lat'] ?>,<?= $row['punch_in_lng'] ?>" 
                                       target="_blank" class="text-xs text-muted" style="display:inline-flex;align-items:center;gap:3px;font-size:0.75rem" title="View Punch-In Location">
                                        <i class="fa fa-map-marker-alt" style="color:var(--c-danger)"></i> GPS Loc
                                    </a>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-sm">
                            <?= $row['punch_out'] ? formatTime($row['punch_out']) : '—' ?>
                            <?php if (!empty($row['punch_out_lat']) && !empty($row['punch_out_lng'])): ?>
                                <div style="margin-top:2px">
                                    <a href="https://www.google.com/maps?q=<?= $row['punch_out_lat'] ?>,<?= $row['punch_out_lng'] ?>" 
                                       target="_blank" class="text-xs text-muted" style="display:inline-flex;align-items:center;gap:3px;font-size:0.75rem" title="View Punch-Out Location">
                                        <i class="fa fa-map-marker-alt" style="color:var(--c-danger)"></i> GPS Loc
                                    </a>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= number_format($row['working_hours'],2) ?>h</strong></td>
                        <td><?= statusBadge($row['status']) ?></td>
                        <td class="text-xs text-muted"><?= htmlspecialchars($row['remarks'] ?? '—') ?></td>

                        <?php elseif ($type === 'leaves'): ?>
                        <td><strong><?= htmlspecialchars($row['student_name']) ?></strong></td>
                        <td><code class="text-xs"><?= htmlspecialchars($row['sid']) ?></code></td>
                        <td class="text-xs text-muted"><?= htmlspecialchars($row['dept_name'] ?? 'General') ?></td>
                        <td><span class="badge badge-info"><?= htmlspecialchars($row['leave_type']) ?></span></td>
                        <td class="text-sm"><?= formatDate($row['from_date']) ?></td>
                        <td class="text-sm"><?= formatDate($row['to_date']) ?></td>
                        <td class="fw-600"><?= $row['days'] ?> day(s)</td>
                        <td><?= statusBadge($row['status']) ?></td>
                        <td class="text-xs text-muted"><?= htmlspecialchars($row['admin_remark'] ?? '—') ?></td>

                        <?php elseif ($type === 'tasks'): ?>
                        <td><strong><?= htmlspecialchars($row['student_name']) ?></strong></td>
                        <td><code class="text-xs"><?= htmlspecialchars($row['sid']) ?></code></td>
                        <td class="text-sm fw-600"><?= htmlspecialchars($row['title']) ?></td>
                        <td class="text-xs text-muted"><?= htmlspecialchars($row['project_title'] ?? 'Independent') ?></td>
                        <td><?= $row['priority'] ?></td>
                        <td class="text-sm"><?= $row['deadline'] ? formatDate($row['deadline']) : 'N/A' ?></td>
                        <td class="fw-600"><?= $row['progress'] ?>%</td>
                        <td><?= statusBadge($row['status']) ?></td>

                        <?php elseif ($type === 'performance'): ?>
                        <td><strong><?= htmlspecialchars($row['student_name']) ?></strong></td>
                        <td><code class="text-xs"><?= htmlspecialchars($row['sid']) ?></code></td>
                        <td class="text-xs text-muted"><?= htmlspecialchars($row['dept_name']) ?></td>
                        <td><?= $row['attendance_pct'] ?></td>
                        <td><?= $row['avg_hours'] ?></td>
                        <td><?= $row['tasks_pct'] ?></td>
                        <td><?= $row['reports_pct'] ?></td>
                        <td><span class="badge badge-success"><?= $row['overall_score'] ?></span></td>
                        <?php endif; ?>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>
</div>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
<script>
function toggleDateFilters(val) {
    const el = document.getElementById('date-filters');
    if (val === 'tasks' || val === 'performance') {
        el.style.display = 'none';
    } else {
        el.style.display = 'flex';
    }
}
</script>
