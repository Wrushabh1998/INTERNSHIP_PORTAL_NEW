<?php
/**
 * Admin — Performance Evaluation
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
$mentorId  = $_SESSION['admin_id'];

if ($isMentor) {
    $students = $pdo->prepare("
        SELECT s.id, s.name, s.student_id, s.profile_photo, d.name as dept_name, b.batch_name
        FROM students s
        LEFT JOIN departments d ON s.department_id = d.id
        LEFT JOIN internship_batches b ON s.batch_id = b.id
        WHERE s.is_active = 1 AND s.mentor_id = ?
        ORDER BY s.name
    ");
    $students->execute([$mentorId]);
} else {
    $students = $pdo->prepare("
        SELECT s.id, s.name, s.student_id, s.profile_photo, d.name as dept_name, b.batch_name
        FROM students s
        LEFT JOIN departments d ON s.department_id = d.id
        LEFT JOIN internship_batches b ON s.batch_id = b.id
        WHERE s.is_active = 1
        ORDER BY s.name
    ");
    $students->execute();
}
$students = $students->fetchAll();

$evaluations = [];

foreach ($students as $student) {
    $sId = $student['id'];

    // 1. Attendance %
    $attPct = getAttendancePercentage($sId);

    // 2. Working Hours
    $hrsStmt = $pdo->prepare("SELECT AVG(working_hours) FROM attendance WHERE student_id=? AND status IN('Present','Late','Half Day')");
    $hrsStmt->execute([$sId]);
    $avgHrs = (float)($hrsStmt->fetchColumn() ?: 0.0);
    $hoursScore = min(100.0, ($avgHrs / 8.0) * 100.0);

    // 3. Task Completion %
    $taskPct = getTaskCompletionPercentage($sId);

    // 4. Daily Reports Score
    $repStmt = $pdo->prepare("
        SELECT 
            COUNT(*) total,
            SUM(status='Approved') approved
        FROM daily_reports WHERE student_id=?
    ");
    $repStmt->execute([$sId]);
    $repStats = $repStmt->fetch();
    $repPct = $repStats['total'] > 0 ? round(($repStats['approved'] / $repStats['total']) * 100, 1) : 0.0;

    // 5. Leave Count
    $leaveStmt = $pdo->prepare("SELECT SUM(days) FROM leave_requests WHERE student_id=? AND status='Approved'");
    $leaveStmt->execute([$sId]);
    $leaveDays = (int)($leaveStmt->fetchColumn() ?: 0);

    // Calculate overall score (weights: Attendance 30%, Tasks 30%, Reports 20%, Hours 20%)
    // Subtract 1.5 points per leave day as a slight index
    $overallScore = ($attPct * 0.3) + ($taskPct * 0.3) + ($repPct * 0.2) + ($hoursScore * 0.2);
    if ($leaveDays > 0) {
        $overallScore = max(0.0, $overallScore - ($leaveDays * 1.0));
    }
    $overallScore = round($overallScore, 1);

    // Star Rating (out of 5)
    $rating = round(($overallScore / 100.0) * 5.0, 1);

    $evaluations[] = [
        'student' => $student,
        'attendance_pct' => $attPct,
        'avg_hours' => $avgHrs,
        'tasks_pct' => $taskPct,
        'reports_pct' => $repPct,
        'leave_days' => $leaveDays,
        'overall_score' => $overallScore,
        'rating' => $rating
    ];
}

// Sort by overall score descending to rank
usort($evaluations, function($a, $b) {
    return $b['overall_score'] <=> $a['overall_score'];
});

$pageTitle  = 'Performance Evaluation';
$userRole   = 'admin';
$breadcrumb = [['label'=>'Performance']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-admin.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">Performance Evaluation</h1>
            <p class="page-subtitle">Ranks and metrics computed automatically based on internship activities</p>
        </div>
    </div>

    <!-- Leaderboard Cards -->
    <div style="display:grid;grid-template-columns: 1fr 1fr 1fr;gap:20px;margin-bottom:24px">
        <?php for ($r=0; $r<3; $r++): if (isset($evaluations[$r])): 
            $ev = $evaluations[$r];
            $badges = ['🏆 Rank 1', '🥈 Rank 2', '🥉 Rank 3'];
        ?>
        <div class="card" style="position:relative; overflow:hidden; border-top: 4px solid <?= $r===0 ? '#f59e0b' : ($r===1 ? '#94a3b8' : '#b45309') ?>">
            <div class="card-body text-center">
                <div style="position:absolute; top:12px; right:12px; font-weight:700" class="text-sm">
                    <?= $badges[$r] ?>
                </div>
                <img src="<?= profilePhotoUrl($ev['student']['profile_photo'], 'student') ?>" class="avatar avatar-lg mb-12" style="margin: 0 auto">
                <h3 style="font-size:1.15rem;margin:0"><?= htmlspecialchars($ev['student']['name']) ?></h3>
                <p class="text-xs text-muted" style="margin:4px 0 12px 0"><?= htmlspecialchars($ev['student']['student_id']) ?> | <?= htmlspecialchars($ev['student']['dept_name'] ?? 'General') ?></p>
                
                <div style="font-size:2rem;font-weight:800;color:var(--primary);line-height:1"><?= $ev['overall_score'] ?>%</div>
                <div class="text-xs text-muted mt-8 mb-12">Overall Index Score</div>

                <div class="text-warning fw-600">
                    <?php
                    $fullStars = floor($ev['rating']);
                    $halfStar = ($ev['rating'] - $fullStars) >= 0.5 ? 1 : 0;
                    for($i=0; $i<$fullStars; $i++) echo '★';
                    if($halfStar) echo '½';
                    for($i=0; $i<(5 - $fullStars - $halfStar); $i++) echo '☆';
                    ?>
                    <span class="text-muted text-xs">(<?= $ev['rating'] ?>/5)</span>
                </div>
            </div>
        </div>
        <?php endif; endfor; ?>
    </div>

    <!-- Comprehensive Roster -->
    <div class="card table-card">
        <div class="card-header">
            <span class="card-title">Ranking &amp; Analytics</span>
            <button onclick="window.print()" class="btn btn-ghost btn-sm"><i class="fa fa-print"></i> Export Print</button>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Rank</th>
                        <th>Student</th>
                        <th>Attendance</th>
                        <th>Avg Hours</th>
                        <th>Task Rate</th>
                        <th>Report Quality</th>
                        <th>Approved Leaves</th>
                        <th>Rating</th>
                        <th>Performance Score</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($evaluations)): ?>
                    <tr><td colspan="9"><div class="empty-state"><div class="empty-icon">📊</div><div class="empty-title">No evaluations calculated</div></div></td></tr>
                    <?php else: foreach ($evaluations as $rank => $ev): ?>
                    <tr>
                        <td class="fw-700">#<?= $rank + 1 ?></td>
                        <td>
                            <div class="d-flex align-center gap-8">
                                <img src="<?= profilePhotoUrl($ev['student']['profile_photo'],'student') ?>" class="avatar avatar-sm" alt="">
                                <div>
                                    <div class="fw-600 text-sm"><?= htmlspecialchars($ev['student']['name']) ?></div>
                                    <div class="text-xs text-muted"><?= htmlspecialchars($ev['student']['student_id']) ?></div>
                                </div>
                            </div>
                        </td>
                        <td><strong><?= $ev['attendance_pct'] ?>%</strong></td>
                        <td class="text-sm"><?= number_format($ev['avg_hours'], 2) ?>h</td>
                        <td>
                            <div class="d-flex align-center gap-6">
                                <div class="progress" style="width:50px">
                                    <div class="progress-bar info" data-value="<?= $ev['tasks_pct'] ?>" style="width:<?= $ev['tasks_pct'] ?>%"></div>
                                </div>
                                <span class="text-xs fw-600"><?= $ev['tasks_pct'] ?>%</span>
                            </div>
                        </td>
                        <td>
                            <div class="d-flex align-center gap-6">
                                <div class="progress" style="width:50px">
                                    <div class="progress-bar success" data-value="<?= $ev['reports_pct'] ?>" style="width:<?= $ev['reports_pct'] ?>%"></div>
                                </div>
                                <span class="text-xs fw-600"><?= $ev['reports_pct'] ?>%</span>
                            </div>
                        </td>
                        <td class="text-center text-sm"><?= $ev['leave_days'] ?> day(s)</td>
                        <td class="text-warning">
                            <?php
                            $fullStars = floor($ev['rating']);
                            for($i=0; $i<$fullStars; $i++) echo '★';
                            for($i=0; $i<(5 - $fullStars); $i++) echo '☆';
                            ?>
                        </td>
                        <td>
                            <span class="badge badge-<?= $ev['overall_score'] >= 85 ? 'success' : ($ev['overall_score'] >= 60 ? 'info' : 'warning') ?>" style="font-size:.85rem;padding:6px 12px">
                                <?= $ev['overall_score'] ?>%
                            </span>
                        </td>
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
