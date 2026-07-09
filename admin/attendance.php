<?php
/**
 * Admin — Attendance Management
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin();
$pdo = db();

$adminUser = getCurrentAdmin();
$isMentor  = $adminUser && ($adminUser['role'] ?? 'mentor') === 'mentor';

// Filters
$dateFilter = clean($_GET['date']    ?? date('Y-m-d'));
$studentId  = (int)($_GET['student'] ?? 0);
$deptId     = (int)($_GET['dept']    ?? 0);
$statusF    = clean($_GET['status']  ?? '');
$page       = max(1, (int)($_GET['page'] ?? 1));
$exportCsv  = isset($_GET['export']) && $_GET['export'] === 'csv';

// Build query
$where  = ['a.date = ?'];
$params = [$dateFilter];
if ($studentId) { $where[] = 'a.student_id=?'; $params[] = $studentId; }
if ($deptId)    { $where[] = 's.department_id=?'; $params[] = $deptId; }
if ($statusF)   { $where[] = 'a.status=?'; $params[] = $statusF; }

if ($isMentor) {
    $where[] = 's.mentor_id = ?';
    $params[] = $adminUser['id'];
}

$whereStr = implode(' AND ', $where);

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM attendance a JOIN students s ON a.student_id=s.id WHERE $whereStr");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();

if ($exportCsv) {
    // Export full without pagination
    $stmt = $pdo->prepare("
        SELECT s.name, s.student_id, s.email, a.date, a.punch_in, a.lunch_start, a.lunch_end, a.punch_out, a.working_hours, a.status, a.remarks
        FROM attendance a JOIN students s ON a.student_id=s.id WHERE $whereStr ORDER BY s.name
    ");
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="attendance_' . $dateFilter . '.csv"');
    $f = fopen('php://output', 'w');
    fputcsv($f, ['Name','Student ID','Email','Date','Punch In','Lunch Start','Lunch End','Punch Out','Working Hours','Status','Remarks']);
    foreach ($rows as $r) {
        fputcsv($f, [$r['name'],$r['student_id'],$r['email'],$r['date'],$r['punch_in'],$r['lunch_start'],$r['lunch_end'],$r['punch_out'],$r['working_hours'],$r['status'],$r['remarks']]);
    }
    fclose($f);
    exit;
}

$pag = paginate($total, $page);
$stmt = $pdo->prepare("
    SELECT a.*, s.name student_name, s.student_id sid, s.profile_photo,
           d.name dept_name
    FROM attendance a
    JOIN students s ON a.student_id=s.id
    LEFT JOIN departments d ON s.department_id=d.id
    WHERE $whereStr
    ORDER BY s.name
    LIMIT {$pag['per_page']} OFFSET {$pag['offset']}
");
$stmt->execute($params);
$records = $stmt->fetchAll();

// Today's summary for the selected date
if ($isMentor) {
    $summaryStmt = $pdo->prepare("SELECT a.status, COUNT(*) c FROM attendance a JOIN students s ON a.student_id=s.id WHERE a.date=? AND s.mentor_id=? GROUP BY a.status");
    $summaryStmt->execute([$dateFilter, $adminUser['id']]);
} else {
    $summaryStmt = $pdo->prepare("SELECT status, COUNT(*) c FROM attendance WHERE date=? GROUP BY status");
    $summaryStmt->execute([$dateFilter]);
}
$summary = array_column($summaryStmt->fetchAll(), 'c', 'status');

if ($isMentor) {
    $studentsStmt = $pdo->prepare("SELECT id, name, student_id FROM students WHERE is_active=1 AND mentor_id=? ORDER BY name");
    $studentsStmt->execute([$adminUser['id']]);
} else {
    $studentsStmt = $pdo->prepare("SELECT id, name, student_id FROM students WHERE is_active=1 ORDER BY name");
    $studentsStmt->execute();
}
$students = $studentsStmt->fetchAll();

$departments = $pdo->query("SELECT * FROM departments ORDER BY name")->fetchAll();

$pageTitle  = 'Attendance Management';
$userRole   = 'admin';
$breadcrumb = [['label'=>'Attendance']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-admin.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">Attendance Management</h1>
            <p class="page-subtitle">Viewing: <?= formatDate($dateFilter, 'l, d F Y') ?></p>
        </div>
        <div class="d-flex gap-8">
            <a href="<?= SITE_URL ?>/admin/attendance-manual.php?date=<?= $dateFilter ?>" class="btn btn-primary btn-sm">
                <i class="fa fa-check-square"></i> Manual Checkbox Attendance
            </a>
            <a href="?<?= http_build_query(['date'=>$dateFilter,'student'=>$studentId,'dept'=>$deptId,'status'=>$statusF,'export'=>'csv']) ?>" class="btn btn-secondary btn-sm">
                <i class="fa fa-file-csv"></i> Export CSV
            </a>
            <button onclick="window.print()" class="btn btn-ghost btn-sm"><i class="fa fa-print"></i> Print</button>
        </div>
    </div>

    <!-- Summary Cards -->
    <div class="stat-grid" style="margin-bottom:20px">
        <div class="stat-card success">
            <div class="stat-icon"><i class="fa fa-check-circle"></i></div>
            <div class="stat-body">
                <div class="stat-value"><?= ((int)($summary['Present']??0)) + ((int)($summary['Late']??0)) + ((int)($summary['Half Day']??0)) ?></div>
                <div class="stat-label">Present</div>
            </div>
        </div>
        <div class="stat-card danger">
            <div class="stat-icon"><i class="fa fa-times-circle"></i></div>
            <div class="stat-body"><div class="stat-value"><?= $summary['Absent']??0 ?></div><div class="stat-label">Absent</div></div>
        </div>
        <div class="stat-card warning">
            <div class="stat-icon"><i class="fa fa-calendar-times"></i></div>
            <div class="stat-body"><div class="stat-value"><?= $summary['Leave']??0 ?></div><div class="stat-label">On Leave</div></div>
        </div>
        <div class="stat-card orange">
            <div class="stat-icon"><i class="fa fa-clock"></i></div>
            <div class="stat-body"><div class="stat-value"><?= $summary['Late']??0 ?></div><div class="stat-label">Late</div></div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon"><i class="fa fa-adjust"></i></div>
            <div class="stat-body"><div class="stat-value"><?= $summary['Half Day']??0 ?></div><div class="stat-label">Half Day</div></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fa fa-users"></i></div>
            <div class="stat-body"><div class="stat-value"><?= $total ?></div><div class="stat-label">Total Records</div></div>
        </div>
    </div>

    <!-- Filters -->
    <div class="card" style="margin-bottom:20px">
        <div class="card-body" style="padding:16px 22px">
            <form method="GET" class="d-flex gap-12 align-center flex-wrap">
                <div class="form-group" style="margin:0">
                    <input type="date" name="date" class="form-control" value="<?= $dateFilter ?>">
                </div>
                <select name="dept" class="form-control" style="width:160px">
                    <option value="">All Departments</option>
                    <?php foreach ($departments as $d): ?>
                    <option value="<?= $d['id'] ?>" <?= $deptId==$d['id']?'selected':'' ?>><?= htmlspecialchars($d['name']) ?></option>
                    <?php endforeach; ?>
                </select>
                <select name="status" class="form-control" style="width:140px">
                    <option value="">All Status</option>
                    <?php foreach (['Present','Absent','Late','Half Day','Leave','Holiday'] as $s): ?>
                    <option <?= $statusF===$s?'selected':'' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa fa-filter"></i> Filter</button>
                <a href="<?= SITE_URL ?>/admin/attendance.php" class="btn btn-secondary btn-sm">Reset</a>
            </form>
        </div>
    </div>

    <!-- Table -->
    <div class="card table-card">
        <div class="card-header">
            <span class="card-title">Attendance Records</span>
            <span class="text-sm text-muted"><?= $total ?> record(s)</span>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Student</th>
                        <th>Punch In</th>
                        <th>Lunch Break</th>
                        <th>Punch Out</th>
                        <th>Working Hrs</th>
                        <th>Status</th>
                        <th>Photos</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($records)): ?>
                    <tr><td colspan="9">
                        <div class="empty-state"><div class="empty-icon">📅</div>
                        <div class="empty-title">No attendance records for this date</div></div>
                    </td></tr>
                    <?php else: foreach ($records as $i => $r): ?>
                    <tr>
                        <td class="text-sm text-muted"><?= $pag['offset']+$i+1 ?></td>
                        <td>
                            <div class="d-flex align-center gap-8">
                                <img src="<?= profilePhotoUrl($r['profile_photo'],'student') ?>" class="avatar avatar-sm" alt="">
                                <div>
                                    <div class="fw-600 text-sm"><?= htmlspecialchars($r['student_name']) ?></div>
                                    <div class="text-xs text-muted"><?= htmlspecialchars($r['sid']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td class="text-sm">
                            <?= $r['punch_in'] ? formatTime($r['punch_in']) : '—' ?>
                            <?php if (!empty($r['punch_in_lat']) && !empty($r['punch_in_lng'])): ?>
                                <div style="margin-top:2px">
                                    <a href="https://www.google.com/maps?q=<?= $r['punch_in_lat'] ?>,<?= $r['punch_in_lng'] ?>" 
                                       target="_blank" class="text-xs text-muted" style="display:inline-flex;align-items:center;gap:3px;font-size:0.75rem" title="View Punch-In Location">
                                        <i class="fa fa-map-marker-alt" style="color:var(--c-danger)"></i> GPS Loc
                                    </a>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td class="text-sm">
                            <?php if ($r['lunch_start']): ?>
                            <?= formatTime($r['lunch_start']) ?> – <?= $r['lunch_end'] ? formatTime($r['lunch_end']) : 'Active' ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>
                        <td class="text-sm">
                            <?= $r['punch_out'] ? formatTime($r['punch_out']) : '—' ?>
                            <?php if (!empty($r['punch_out_lat']) && !empty($r['punch_out_lng'])): ?>
                                <div style="margin-top:2px">
                                    <a href="https://www.google.com/maps?q=<?= $r['punch_out_lat'] ?>,<?= $r['punch_out_lng'] ?>" 
                                       target="_blank" class="text-xs text-muted" style="display:inline-flex;align-items:center;gap:3px;font-size:0.75rem" title="View Punch-Out Location">
                                        <i class="fa fa-map-marker-alt" style="color:var(--c-danger)"></i> GPS Loc
                                    </a>
                                </div>
                            <?php endif; ?>
                        </td>
                        <td><strong><?= number_format($r['working_hours'],2) ?>h</strong></td>
                        <td><?= statusBadge($r['status']) ?></td>
                        <td>
                            <?php
                            $imgs = $pdo->prepare("SELECT * FROM attendance_images WHERE attendance_id=?");
                            $imgs->execute([$r['id']]);
                            $imgList = $imgs->fetchAll();
                            foreach ($imgList as $img):
                            ?>
                            <a href="<?= SITE_URL ?>/uploads/attendance/<?= htmlspecialchars($img['image_path']) ?>"
                               target="_blank"
                               style="display:inline-block;margin:1px">
                                <img src="<?= SITE_URL ?>/uploads/attendance/<?= htmlspecialchars($img['image_path']) ?>"
                                     style="width:32px;height:32px;border-radius:6px;object-fit:cover;border:1px solid var(--border)"
                                     title="<?= $img['type'] ?>">
                            </a>
                            <?php endforeach; if (empty($imgList)) echo '<span class="text-muted text-xs">No photos</span>'; ?>
                        </td>
                        <td>
                            <a href="<?= SITE_URL ?>/admin/attendance-edit.php?id=<?= $r['id'] ?>" class="btn btn-ghost btn-icon-sm" title="Edit/Correct">
                                <i class="fa fa-edit"></i>
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
        <div class="card-footer">
            <?= renderPagination($pag, SITE_URL . '/admin/attendance.php?' . http_build_query(['date'=>$dateFilter,'dept'=>$deptId,'status'=>$statusF])) ?>
        </div>
    </div>

</main>
</div>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
