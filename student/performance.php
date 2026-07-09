<?php
/**
 * Student — Performance Analytics Dashboard
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

requireStudent();
$pdo = db();
$sId = $_SESSION['student_id'];
$student = getCurrentStudent();

// 1. Attendance %
$attPct = getAttendancePercentage($sId);

// 2. Working Hours
$hrsStmt = $pdo->prepare("SELECT AVG(working_hours) FROM attendance WHERE student_id=? AND status IN('Present','Late','Half Day')");
$hrsStmt->execute([$sId]);
$avgHrs = round((float)($hrsStmt->fetchColumn() ?: 0.0), 1);
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
$overallScore = ($attPct * 0.3) + ($taskPct * 0.3) + ($repPct * 0.2) + ($hoursScore * 0.2);
if ($leaveDays > 0) {
    $overallScore = max(0.0, $overallScore - ($leaveDays * 1.0));
}
$overallScore = round($overallScore, 1);

// Star Rating (out of 5)
$rating = round(($overallScore / 100.0) * 5.0, 1);

$pageTitle  = 'My Performance';
$userRole   = 'student';
$breadcrumb = [['label'=>'Performance']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-student.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">Performance Analytics 📈</h1>
            <p class="page-subtitle">Personal scorecard compiled automatically from system logs</p>
        </div>
    </div>

    <!-- Rating Overview Card -->
    <div style="display:grid; grid-template-columns: 1fr 2fr; gap:20px; margin-bottom:24px">
        
        <div class="card text-center" style="padding:24px; display:flex; flex-direction:column; justify-content:center">
            <div class="text-xs text-muted" style="text-transform:uppercase; letter-spacing:0.1em; font-weight:700">Overall Rating</div>
            <div style="font-size:3.5rem; font-weight:800; color:var(--primary); margin:8px 0 0 0; line-height:1"><?= $rating ?></div>
            <div class="text-xs text-muted mb-12">out of 5 stars</div>
            
            <div class="text-warning" style="font-size:1.4rem">
                <?php
                $fullStars = floor($rating);
                for($i=0; $i<$fullStars; $i++) echo '★';
                for($i=0; $i<(5 - $fullStars); $i++) echo '☆';
                ?>
            </div>
            
            <hr class="divider">
            <div class="text-xs text-muted">Based on your activity logs since starting your internship.</div>
        </div>

        <div class="card">
            <div class="card-header"><span class="card-title">Scorecard Breakdown</span></div>
            <div class="card-body">
                
                <!-- Attendance -->
                <div class="mb-16">
                    <div class="d-flex justify-between align-center text-sm mb-0" style="margin-bottom:6px">
                        <span><i class="fa fa-calendar-check" style="color:var(--c-success)"></i> Attendance Score</span>
                        <span class="fw-700"><?= $attPct ?>%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar success" data-value="<?= $attPct ?>" style="width:<?= $attPct ?>%"></div>
                    </div>
                </div>

                <!-- Tasks -->
                <div class="mb-16">
                    <div class="d-flex justify-between align-center text-sm mb-0" style="margin-bottom:6px">
                        <span><i class="fa fa-tasks" style="color:var(--primary)"></i> Task Completion Score</span>
                        <span class="fw-700"><?= $taskPct ?>%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar" data-value="<?= $taskPct ?>" style="width:<?= $taskPct ?>%"></div>
                    </div>
                </div>

                <!-- Daily Reports -->
                <div class="mb-16">
                    <div class="d-flex justify-between align-center text-sm mb-0" style="margin-bottom:6px">
                        <span><i class="fa fa-file-alt" style="color:var(--c-orange)"></i> Daily Report Quality (Approved Ratio)</span>
                        <span class="fw-700"><?= $repPct ?>%</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar orange" data-value="<?= $repPct ?>" style="width:<?= $repPct ?>%"></div>
                    </div>
                </div>

                <!-- Avg Hours -->
                <div>
                    <div class="d-flex justify-between align-center text-sm mb-0" style="margin-bottom:6px">
                        <span><i class="fa fa-clock" style="color:var(--c-info)"></i> Average Working Hours Score (vs 8h)</span>
                        <span class="fw-700"><?= $avgHrs ?>h (<?= round($hoursScore) ?>%)</span>
                    </div>
                    <div class="progress">
                        <div class="progress-bar info" data-value="<?= $hoursScore ?>" style="width:<?= $hoursScore ?>%"></div>
                    </div>
                </div>

            </div>
        </div>

    </div>

    <!-- Overall Metrics Cards -->
    <div class="stat-grid">
        <div class="stat-card success">
            <div class="stat-icon"><i class="fa fa-star"></i></div>
            <div class="stat-body">
                <div class="stat-value"><?= $overallScore ?>%</div>
                <div class="stat-label">Performance Score</div>
            </div>
        </div>
        <div class="stat-card danger">
            <div class="stat-icon"><i class="fa fa-calendar-times"></i></div>
            <div class="stat-body">
                <div class="stat-value"><?= $leaveDays ?></div>
                <div class="stat-label">Approved Leaves (Days)</div>
            </div>
        </div>
        <div class="stat-card info">
            <div class="stat-icon"><i class="fa fa-clipboard-list"></i></div>
            <div class="stat-body">
                <div class="stat-value"><?= $repStats['total'] ?></div>
                <div class="stat-label">Daily Reports Submitted</div>
            </div>
        </div>
    </div>

</main>
</div>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
