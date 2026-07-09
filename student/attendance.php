<?php
/**
 * Student — Attendance Marking Panel
 * Student marks their own punch in / lunch / punch out with GPS capture.
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

requireStudent();
$pdo   = db();
$sId   = $_SESSION['student_id'];
$today = date('Y-m-d');

// Fetch student info
$student = getCurrentStudent();

// Fetch today's record
$todayStmt = $pdo->prepare("SELECT * FROM attendance WHERE student_id=? AND date=?");
$todayStmt->execute([$sId, $today]);
$todayAtt = $todayStmt->fetch();
if (!$todayAtt) {
    $todayAtt = ['status'=>'Not Marked','punch_in'=>null,'lunch_start'=>null,'lunch_end'=>null,'punch_out'=>null,'working_hours'=>0];
}

$punchIn    = $todayAtt['punch_in'];
$lunchStart = $todayAtt['lunch_start'];
$lunchEnd   = $todayAtt['lunch_end'];
$punchOut   = $todayAtt['punch_out'];

// Live clock offset — used in JS
$serverTs = time();

// Attendance calendar data (current month)
$month = (int)date('m'); $year = (int)date('Y');
$calStmt = $pdo->prepare("SELECT date, status FROM attendance WHERE student_id=? AND YEAR(date)=? AND MONTH(date)=?");
$calStmt->execute([$sId, $year, $month]);
$calData = [];
foreach ($calStmt->fetchAll() as $r) { $calData[$r['date']] = $r['status']; }

// History (last 15 records with GPS)
$histStmt = $pdo->prepare("
    SELECT a.*, ai_in.image_path pi_img, ai_out.image_path po_img
    FROM attendance a
    LEFT JOIN attendance_images ai_in  ON ai_in.attendance_id=a.id  AND ai_in.type='punch_in'
    LEFT JOIN attendance_images ai_out ON ai_out.attendance_id=a.id AND ai_out.type='punch_out'
    WHERE a.student_id=? ORDER BY a.date DESC LIMIT 15");
$histStmt->execute([$sId]);
$history = $histStmt->fetchAll();

// Summary stats
$summaryStmt = $pdo->prepare("SELECT
    COUNT(*) total,
    SUM(status='Present') present,
    SUM(status='Late') late,
    SUM(status='Half Day') half,
    SUM(status='Absent') absent,
    SUM(status='Leave') leave_days,
    ROUND(AVG(working_hours),2) avg_hours,
    ROUND(SUM(working_hours),2) total_hours
    FROM attendance WHERE student_id=?");
$summaryStmt->execute([$sId]);
$summary = $summaryStmt->fetch();
$pct = ($summary['total'] > 0)
    ? round(($summary['present']+$summary['late']+$summary['half'])/$summary['total']*100,1) : 0;

$pageTitle  = 'Mark Attendance';
$userRole   = 'student';
$breadcrumb = [['label'=>'Attendance']];
$extraJS    = ['attendance.js'];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-student.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

<style>
/* ══════════════════════════════════════════
   ATTENDANCE PAGE — PREMIUM PUNCH PANEL
══════════════════════════════════════════ */

/* Live Clock */
.att-clock-bar {
    background: linear-gradient(135deg, #1e1b4b 0%, #312e81 50%, #4338ca 100%);
    border-radius: 16px;
    padding: 20px 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 24px;
    box-shadow: 0 8px 32px rgba(67,56,202,.35);
    color: #fff;
    flex-wrap: wrap;
    gap: 12px;
}
.att-clock-time {
    font-size: 2.4rem;
    font-weight: 800;
    letter-spacing: 2px;
    font-variant-numeric: tabular-nums;
    line-height: 1;
}
.att-clock-date {
    font-size: 0.92rem;
    opacity: 0.75;
    margin-top: 4px;
    letter-spacing: 0.3px;
}
.att-clock-status .badge-status {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 6px 16px;
    border-radius: 50px;
    font-size: 0.82rem;
    font-weight: 700;
    letter-spacing: 0.5px;
}
.badge-status.not-marked   { background: rgba(255,255,255,.12); color: rgba(255,255,255,.85); border: 1px solid rgba(255,255,255,.2); }
.badge-status.present      { background: rgba(34,197,94,.25);  color: #86efac; border: 1px solid rgba(34,197,94,.4); }
.badge-status.late         { background: rgba(245,158,11,.25); color: #fde68a; border: 1px solid rgba(245,158,11,.4); }
.badge-status.complete     { background: rgba(34,197,94,.3);   color: #6ee7b7; border: 1px solid rgba(34,197,94,.5); }

/* Punch Panel */
.punch-panel {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 18px;
    overflow: hidden;
    margin-bottom: 24px;
    box-shadow: 0 4px 24px rgba(0,0,0,.07);
}
.punch-panel-header {
    padding: 20px 24px 0;
    display: flex;
    align-items: center;
    justify-content: space-between;
}
.punch-panel-title {
    font-size: 1.05rem;
    font-weight: 700;
    color: var(--text-primary);
    display: flex;
    align-items: center;
    gap: 8px;
}
.punch-panel-body {
    padding: 20px 24px 24px;
}

/* Punch Step Track */
.punch-steps {
    display: grid;
    grid-template-columns: 1fr auto 1fr auto 1fr auto 1fr;
    align-items: center;
    gap: 0;
    margin-bottom: 24px;
}
.punch-step {
    text-align: center;
    position: relative;
}
.punch-step-connector {
    height: 3px;
    background: var(--border);
    position: relative;
    top: -18px;
}
.punch-step-connector.done {
    background: linear-gradient(90deg, #22c55e, #16a34a);
}
.punch-step-icon {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.15rem;
    margin: 0 auto 8px;
    border: 2px solid var(--border);
    color: var(--text-muted);
    background: var(--bg-secondary);
    transition: all .3s;
}
.punch-step-icon.done {
    background: linear-gradient(135deg, #22c55e, #16a34a);
    border-color: #22c55e;
    color: #fff;
    box-shadow: 0 4px 12px rgba(34,197,94,.4);
}
.punch-step-icon.active {
    background: linear-gradient(135deg, #6366f1, #4f46e5);
    border-color: #6366f1;
    color: #fff;
    box-shadow: 0 4px 12px rgba(99,102,241,.4);
    animation: pulse-ring 1.8s ease-in-out infinite;
}
.punch-step-icon.lunch-done {
    background: linear-gradient(135deg, #f59e0b, #d97706);
    border-color: #f59e0b;
    color: #fff;
    box-shadow: 0 4px 12px rgba(245,158,11,.4);
}
.punch-step-label {
    font-size: 0.72rem;
    font-weight: 600;
    color: var(--text-muted);
    text-transform: uppercase;
    letter-spacing: 0.5px;
}
.punch-step-time {
    font-size: 0.78rem;
    font-weight: 700;
    color: var(--c-success);
    margin-top: 2px;
}
@keyframes pulse-ring {
    0%   { box-shadow: 0 0 0 0 rgba(99,102,241,.5); }
    70%  { box-shadow: 0 0 0 10px rgba(99,102,241,0); }
    100% { box-shadow: 0 0 0 0 rgba(99,102,241,0); }
}

/* Main Punch Buttons */
.punch-buttons-grid {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr 1fr;
    gap: 14px;
}
@media (max-width: 760px) {
    .punch-buttons-grid { grid-template-columns: 1fr 1fr; }
    .punch-steps { grid-template-columns: 1fr; }
    .punch-step-connector { display: none; }
}
.punch-btn-card {
    border-radius: 14px;
    padding: 20px 16px;
    text-align: center;
    cursor: pointer;
    border: 2px solid transparent;
    transition: all .25s cubic-bezier(.34,1.56,.64,1);
    position: relative;
    overflow: hidden;
    user-select: none;
    font-family: inherit;
    width: 100%;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
}
.punch-btn-card:not(:disabled):hover {
    transform: translateY(-4px);
    box-shadow: 0 12px 32px rgba(0,0,0,.2);
}
.punch-btn-card:not(:disabled):active { transform: translateY(0); }
.punch-btn-card:disabled {
    cursor: not-allowed;
    opacity: 0.7;
}
.punch-btn-card.punch-in-btn {
    background: linear-gradient(145deg, #22c55e, #15803d);
    color: #fff;
    border-color: #22c55e;
}
.punch-btn-card.lunch-start-btn {
    background: linear-gradient(145deg, #f59e0b, #b45309);
    color: #fff;
    border-color: #f59e0b;
}
.punch-btn-card.lunch-end-btn {
    background: linear-gradient(145deg, #06b6d4, #0e7490);
    color: #fff;
    border-color: #06b6d4;
}
.punch-btn-card.punch-out-btn {
    background: linear-gradient(145deg, #ef4444, #b91c1c);
    color: #fff;
    border-color: #ef4444;
}
.punch-btn-card.done-btn {
    background: var(--bg-secondary);
    border: 2px solid var(--border);
    color: var(--text-muted);
    cursor: default;
}
.punch-btn-card.done-btn:hover { transform: none; box-shadow: none; }
.punch-btn-icon {
    width: 52px;
    height: 52px;
    border-radius: 50%;
    background: rgba(255,255,255,.2);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.4rem;
    margin: 0 auto;
}
.punch-btn-card.done-btn .punch-btn-icon {
    background: var(--border);
    color: var(--text-muted);
    font-size: 1.2rem;
}
.punch-btn-label {
    font-size: 0.88rem;
    font-weight: 700;
    letter-spacing: 0.4px;
    text-transform: uppercase;
}
.punch-btn-sublabel {
    font-size: 0.72rem;
    opacity: 0.8;
    font-weight: 500;
}
.punch-btn-card .spinner {
    width: 22px; height: 22px;
    border-radius: 50%;
    border: 3px solid rgba(255,255,255,.3);
    border-top-color: #fff;
    animation: spin .6s linear infinite;
    display: inline-block;
}
@keyframes spin { to { transform: rotate(360deg); } }

/* Complete banner */
.attendance-complete-banner {
    background: linear-gradient(135deg, rgba(34,197,94,.12), rgba(16,185,129,.08));
    border: 1.5px solid rgba(34,197,94,.35);
    border-radius: 12px;
    padding: 16px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    margin-top: 16px;
}
.att-complete-icon {
    width: 42px; height: 42px;
    border-radius: 50%;
    background: linear-gradient(135deg, #22c55e, #16a34a);
    display: flex; align-items: center; justify-content: center;
    color: #fff;
    font-size: 1.2rem;
    flex-shrink: 0;
}

/* GPS indicator */
.gps-indicator {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.72rem;
    color: var(--text-muted);
    margin-top: 4px;
    padding: 3px 8px;
    background: rgba(99,102,241,.08);
    border-radius: 20px;
    border: 1px solid rgba(99,102,241,.15);
}
.gps-dot {
    width: 6px; height: 6px;
    border-radius: 50%;
    background: #6366f1;
    animation: gps-blink 1.4s ease-in-out infinite;
}
@keyframes gps-blink { 0%,100%{opacity:1} 50%{opacity:.2} }

/* Summary Stats Row */
.att-stat-row {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 12px;
    margin-bottom: 20px;
}
@media(max-width:700px){ .att-stat-row { grid-template-columns: repeat(2,1fr); } }
.att-stat-box {
    background: var(--card-bg);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 14px 10px;
    text-align: center;
}
.att-stat-box .val { font-size: 1.5rem; font-weight: 800; line-height: 1; }
.att-stat-box .lbl { font-size: 0.7rem; color: var(--text-muted); margin-top: 4px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; }
</style>

    <div class="page-header" style="margin-bottom:18px">
        <div>
            <h1 class="page-title">Mark Attendance</h1>
            <p class="page-subtitle">
                <?= htmlspecialchars($student['name'] ?? 'Student') ?> &nbsp;·&nbsp;
                <code><?= htmlspecialchars($student['student_id'] ?? '') ?></code>
            </p>
        </div>
    </div>

    <!-- Live Clock Bar -->
    <div class="att-clock-bar">
        <div>
            <div class="att-clock-time" id="live-clock">--:--:--</div>
            <div class="att-clock-date"><?= date('l, d F Y') ?></div>
        </div>
        <div class="att-clock-status">
            <?php if ($punchIn && $punchOut): ?>
                <span class="badge-status complete"><i class="fa fa-check-circle"></i> Attendance Complete</span>
            <?php elseif ($punchIn): ?>
                <span class="badge-status <?= $todayAtt['status'] === 'Late' ? 'late' : 'present' ?>">
                    <i class="fa fa-circle"></i> Punched In &nbsp;<?= ($todayAtt['status']==='Late') ? '(Late)' : '' ?>
                </span>
            <?php else: ?>
                <span class="badge-status not-marked"><i class="fa fa-clock"></i> Not Marked Yet</span>
            <?php endif; ?>
        </div>
    </div>

    <!-- ═══ PUNCH PANEL ═══════════════════════════════════════════════ -->
    <div class="punch-panel">
        <div class="punch-panel-header">
            <div class="punch-panel-title">
                <i class="fa fa-fingerprint" style="color:#6366f1;font-size:1.2rem"></i>
                Daily Attendance — Self Mark
            </div>
            <div class="gps-indicator">
                <span class="gps-dot"></span> GPS Auto-Capture on Punch
            </div>
        </div>
        <div class="punch-panel-body">

            <!-- Step Progress Track -->
            <div class="punch-steps">
                <!-- Punch In -->
                <div class="punch-step">
                    <div class="punch-step-icon <?= $punchIn ? 'done' : 'active' ?>">
                        <i class="fa <?= $punchIn ? 'fa-check' : 'fa-sign-in-alt' ?>"></i>
                    </div>
                    <div class="punch-step-label">Punch In</div>
                    <?php if ($punchIn): ?>
                    <div class="punch-step-time"><?= formatTime($punchIn) ?></div>
                    <?php endif; ?>
                </div>

                <div class="punch-step-connector <?= ($punchIn) ? 'done' : '' ?>"></div>

                <!-- Lunch Start -->
                <div class="punch-step">
                    <div class="punch-step-icon <?= $lunchStart ? 'lunch-done' : ($punchIn && !$punchOut ? 'active' : '') ?>">
                        <i class="fa fa-utensils"></i>
                    </div>
                    <div class="punch-step-label">Lunch Start</div>
                    <?php if ($lunchStart): ?>
                    <div class="punch-step-time"><?= formatTime($lunchStart) ?></div>
                    <?php endif; ?>
                </div>

                <div class="punch-step-connector <?= ($lunchStart) ? 'done' : '' ?>"></div>

                <!-- Lunch End -->
                <div class="punch-step">
                    <div class="punch-step-icon <?= $lunchEnd ? 'lunch-done' : ($lunchStart && !$lunchEnd ? 'active' : '') ?>">
                        <i class="fa fa-coffee"></i>
                    </div>
                    <div class="punch-step-label">Lunch End</div>
                    <?php if ($lunchEnd): ?>
                    <div class="punch-step-time"><?= formatTime($lunchEnd) ?></div>
                    <?php endif; ?>
                </div>

                <div class="punch-step-connector <?= ($punchOut) ? 'done' : '' ?>"></div>

                <!-- Punch Out -->
                <div class="punch-step">
                    <div class="punch-step-icon <?= $punchOut ? 'done' : ($punchIn && !$punchOut ? 'active' : '') ?>">
                        <i class="fa <?= $punchOut ? 'fa-check' : 'fa-sign-out-alt' ?>"></i>
                    </div>
                    <div class="punch-step-label">Punch Out</div>
                    <?php if ($punchOut): ?>
                    <div class="punch-step-time"><?= formatTime($punchOut) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ═══ FOUR BIG BUTTONS ═══ -->
            <input type="hidden" id="att-csrf" value="<?= generateCsrfToken() ?>">

            <div class="punch-buttons-grid">

                <!-- 1. PUNCH IN -->
                <?php if (!$punchIn): ?>
                <button id="btn-punch-in" class="punch-btn-card punch-in-btn btn-punch" type="button">
                    <div class="punch-btn-icon"><i class="fa fa-sign-in-alt"></i></div>
                    <div class="punch-btn-label">Punch In</div>
                    <div class="punch-btn-sublabel">Start your day</div>
                </button>
                <?php else: ?>
                <button class="punch-btn-card done-btn" type="button" disabled>
                    <div class="punch-btn-icon"><i class="fa fa-check"></i></div>
                    <div class="punch-btn-label" style="color:var(--c-success)">Punched In</div>
                    <div class="punch-btn-sublabel"><?= formatTime($punchIn) ?></div>
                </button>
                <?php endif; ?>

                <!-- 2. LUNCH START -->
                <?php if ($punchIn && !$lunchStart && !$punchOut): ?>
                <button id="btn-lunch-start" class="punch-btn-card lunch-start-btn btn-punch" type="button">
                    <div class="punch-btn-icon"><i class="fa fa-utensils"></i></div>
                    <div class="punch-btn-label">Lunch Start</div>
                    <div class="punch-btn-sublabel">Begin break</div>
                </button>
                <?php else: ?>
                <button class="punch-btn-card done-btn" type="button" disabled>
                    <div class="punch-btn-icon"><i class="fa fa-utensils"></i></div>
                    <div class="punch-btn-label" style="color:<?= $lunchStart ? 'var(--c-warning)' : 'var(--text-muted)' ?>">
                        <?= $lunchStart ? 'Lunch Started' : 'Lunch Start' ?>
                    </div>
                    <div class="punch-btn-sublabel"><?= $lunchStart ? formatTime($lunchStart) : 'Punch in first' ?></div>
                </button>
                <?php endif; ?>

                <!-- 3. LUNCH END -->
                <?php if ($lunchStart && !$lunchEnd): ?>
                <button id="btn-lunch-end" class="punch-btn-card lunch-end-btn btn-punch" type="button">
                    <div class="punch-btn-icon"><i class="fa fa-coffee"></i></div>
                    <div class="punch-btn-label">Lunch End</div>
                    <div class="punch-btn-sublabel">Resume work</div>
                </button>
                <?php else: ?>
                <button class="punch-btn-card done-btn" type="button" disabled>
                    <div class="punch-btn-icon"><i class="fa fa-coffee"></i></div>
                    <div class="punch-btn-label" style="color:<?= $lunchEnd ? 'var(--c-info)' : 'var(--text-muted)' ?>">
                        <?= $lunchEnd ? 'Lunch Ended' : 'Lunch End' ?>
                    </div>
                    <div class="punch-btn-sublabel"><?= $lunchEnd ? formatTime($lunchEnd) : 'After lunch start' ?></div>
                </button>
                <?php endif; ?>

                <!-- 4. PUNCH OUT -->
                <?php if ($punchIn && !$punchOut): ?>
                <button id="btn-punch-out" class="punch-btn-card punch-out-btn btn-punch" type="button">
                    <div class="punch-btn-icon"><i class="fa fa-sign-out-alt"></i></div>
                    <div class="punch-btn-label">Punch Out</div>
                    <div class="punch-btn-sublabel">End your day</div>
                </button>
                <?php else: ?>
                <button class="punch-btn-card done-btn" type="button" disabled>
                    <div class="punch-btn-icon"><i class="fa <?= $punchOut ? 'fa-check' : 'fa-sign-out-alt' ?>"></i></div>
                    <div class="punch-btn-label" style="color:<?= $punchOut ? 'var(--c-danger)' : 'var(--text-muted)' ?>">
                        <?= $punchOut ? 'Punched Out' : 'Punch Out' ?>
                    </div>
                    <div class="punch-btn-sublabel"><?= $punchOut ? formatTime($punchOut) : 'After punch in' ?></div>
                </button>
                <?php endif; ?>
            </div>

            <!-- Completion Banner -->
            <?php if ($punchIn && $punchOut): ?>
            <div class="attendance-complete-banner">
                <div class="att-complete-icon"><i class="fa fa-check"></i></div>
                <div>
                    <div class="fw-700" style="color:var(--c-success);font-size:.95rem">✅ Attendance Complete for Today!</div>
                    <div class="text-sm text-muted" style="margin-top:2px">
                        Working Hours: <strong><?= number_format($todayAtt['working_hours'],2) ?>h</strong>
                        &nbsp;·&nbsp; Status: <?= statusBadge($todayAtt['status']) ?>
                        <?php if ($lunchStart && $lunchEnd): ?>
                            &nbsp;·&nbsp; Lunch: <?= formatTime($lunchStart) ?> – <?= formatTime($lunchEnd) ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /punch-panel-body -->
    </div><!-- /punch-panel -->

    <!-- ═══ SUMMARY + CALENDAR ═══════════════════════════════════════ -->
    <div class="att-stat-row">
        <div class="att-stat-box">
            <div class="val" style="color:var(--c-success)"><?= ($summary['present']??0)+($summary['late']??0)+($summary['half']??0) ?></div>
            <div class="lbl">Present</div>
        </div>
        <div class="att-stat-box">
            <div class="val" style="color:var(--c-warning)"><?= $summary['late']??0 ?></div>
            <div class="lbl">Late</div>
        </div>
        <div class="att-stat-box">
            <div class="val" style="color:var(--c-danger)"><?= $summary['absent']??0 ?></div>
            <div class="lbl">Absent</div>
        </div>
        <div class="att-stat-box">
            <div class="val" style="color:var(--primary)"><?= $summary['total_hours']??0 ?>h</div>
            <div class="lbl">Total Hours</div>
        </div>
        <div class="att-stat-box">
            <div class="val" style="color:var(--c-info)"><?= $pct ?>%</div>
            <div class="lbl">Attendance %</div>
        </div>
    </div>

    <!-- ═══ CALENDAR ═════════════════════════════════════════════════ -->
    <div class="card" style="margin-bottom:24px">
        <div class="card-header"><span class="card-title">📅 Monthly Calendar</span></div>
        <div class="card-body"><div id="att-calendar"></div></div>
    </div>

    <!-- ═══ HISTORY TABLE ════════════════════════════════════════════ -->
    <div class="card table-card">
        <div class="card-header">
            <span class="card-title">📋 Attendance Log — Punch In / Out History</span>
            <span class="text-sm text-muted"><?= count($history) ?> records</span>
        </div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Punch In</th>
                        <th>Lunch</th>
                        <th>Punch Out</th>
                        <th>Hrs</th>
                        <th>Status</th>
                        <th>Location</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($history)): ?>
                    <tr><td colspan="7"><div class="empty-state"><div class="empty-icon">📋</div><div class="empty-title">No attendance records yet</div></div></td></tr>
                    <?php endif; ?>
                    <?php foreach ($history as $h): ?>
                    <tr>
                        <td class="fw-600 text-sm"><?= formatDate($h['date'],'d M Y') ?><br><span class="text-xs text-muted"><?= formatDate($h['date'],'(D)') ?></span></td>

                        <td class="text-sm">
                            <?php if ($h['punch_in']): ?>
                                <span class="fw-600" style="color:var(--c-success)"><?= formatTime($h['punch_in']) ?></span>
                                <?php if (!empty($h['punch_in_lat']) && !empty($h['punch_in_lng'])): ?>
                                <div style="margin-top:3px">
                                    <a href="https://www.google.com/maps?q=<?= $h['punch_in_lat'] ?>,<?= $h['punch_in_lng'] ?>"
                                       target="_blank" style="display:inline-flex;align-items:center;gap:3px;font-size:0.7rem;color:var(--text-muted);text-decoration:none">
                                        <i class="fa fa-map-marker-alt" style="color:#ef4444"></i> <?= $h['punch_in_lat'] ?>, <?= $h['punch_in_lng'] ?>
                                    </a>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>

                        <td class="text-sm">
                            <?php if ($h['lunch_start']): ?>
                            <span style="color:var(--c-warning)"><?= formatTime($h['lunch_start']) ?></span>
                            <?php if ($h['lunch_end']): ?> – <span style="color:var(--c-info)"><?= formatTime($h['lunch_end']) ?></span><?php else: ?> …<?php endif; ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>

                        <td class="text-sm">
                            <?php if ($h['punch_out']): ?>
                                <span class="fw-600" style="color:var(--c-danger)"><?= formatTime($h['punch_out']) ?></span>
                                <?php if (!empty($h['punch_out_lat']) && !empty($h['punch_out_lng'])): ?>
                                <div style="margin-top:3px">
                                    <a href="https://www.google.com/maps?q=<?= $h['punch_out_lat'] ?>,<?= $h['punch_out_lng'] ?>"
                                       target="_blank" style="display:inline-flex;align-items:center;gap:3px;font-size:0.7rem;color:var(--text-muted);text-decoration:none">
                                        <i class="fa fa-map-marker-alt" style="color:#ef4444"></i> <?= $h['punch_out_lat'] ?>, <?= $h['punch_out_lng'] ?>
                                    </a>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>—<?php endif; ?>
                        </td>

                        <td><strong><?= number_format($h['working_hours'],2) ?>h</strong></td>
                        <td><?= statusBadge($h['status']) ?></td>

                        <td class="text-xs text-muted">
                            <?php
                            $hasPiGps = !empty($h['punch_in_lat']) && !empty($h['punch_in_lng']);
                            $hasPOutGps = !empty($h['punch_out_lat']) && !empty($h['punch_out_lng']);
                            ?>
                            <?php if ($hasPiGps || $hasPOutGps): ?>
                                <div style="display:flex;flex-direction:column;gap:3px">
                                    <?php if ($hasPiGps): ?>
                                    <a href="https://www.google.com/maps?q=<?= $h['punch_in_lat'] ?>,<?= $h['punch_in_lng'] ?>" target="_blank"
                                       style="display:inline-flex;align-items:center;gap:3px;color:var(--c-success);font-size:0.7rem;text-decoration:none">
                                       <i class="fa fa-map-marker-alt"></i> In GPS
                                    </a>
                                    <?php endif; ?>
                                    <?php if ($hasPOutGps): ?>
                                    <a href="https://www.google.com/maps?q=<?= $h['punch_out_lat'] ?>,<?= $h['punch_out_lng'] ?>" target="_blank"
                                       style="display:inline-flex;align-items:center;gap:3px;color:var(--c-danger);font-size:0.7rem;text-decoration:none">
                                       <i class="fa fa-map-marker-alt"></i> Out GPS
                                    </a>
                                    <?php endif; ?>
                                </div>
                            <?php else: ?>
                                <span style="color:var(--text-muted)">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>
</div>
</div>

<script>
// ── Live clock ─────────────────────────────────────────────────
(function(){
    function tick(){
        const el = document.getElementById('live-clock');
        if (el) el.textContent = new Date().toLocaleTimeString('en-IN',{hour:'2-digit',minute:'2-digit',second:'2-digit',hour12:true});
    }
    tick(); setInterval(tick, 1000);
})();

// ── Self-contained Punch Button Handler ────────────────────────
// This runs inline (no DOMContentLoaded), so buttons always work.
(function(){
    'use strict';

    var AJAX_URL = '/INTERNSHIP_PORTAL_NEW/ajax/attendance.php';

    function getCsrf(){
        var el = document.getElementById('att-csrf');
        if (el) return el.value;
        var el2 = document.querySelector('input[name="_csrf_token"]');
        return el2 ? el2.value : '';
    }

    function punchLabel(type){
        return {punch_in:'Punched In ✅',punch_out:'Punched Out 🔴',lunch_start:'Lunch Started 🍽️',lunch_end:'Lunch Ended ✅'}[type] || 'Done';
    }

    function doPost(type, lat, lng){
        var btn = document.getElementById('btn-' + type.replace(/_/g,'-'));
        if (!btn || btn.disabled) return;
        var origHTML = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner"></span>';

        var fd = new FormData();
        fd.append('action', type);
        fd.append('image', '');
        fd.append('_csrf_token', getCsrf());
        fd.append('user_agent', navigator.userAgent);
        if (lat) fd.append('lat', lat);
        if (lng) fd.append('lng', lng);

        fetch(AJAX_URL, {method:'POST', body:fd})
            .then(function(r){ return r.json(); })
            .then(function(res){
                if (res.success){
                    if (window.Toast) Toast.show('success', punchLabel(type), res.message);
                    else alert(res.message);
                    setTimeout(function(){ location.reload(); }, 1400);
                } else {
                    if (window.Toast) Toast.show('error', 'Action Failed', res.message);
                    else alert('Error: ' + res.message);
                    btn.disabled = false;
                    btn.innerHTML = origHTML;
                }
            })
            .catch(function(err){
                console.error('Punch AJAX error:', err);
                if (window.Toast) Toast.show('error', 'Network Error', 'Could not reach server. Check your connection.');
                else alert('Network Error. Please try again.');
                btn.disabled = false;
                btn.innerHTML = origHTML;
            });
    }

    function punch(type){
        // GPS only for punch_in and punch_out
        if ((type === 'punch_in' || type === 'punch_out') && navigator.geolocation){
            navigator.geolocation.getCurrentPosition(
                function(pos){ doPost(type, pos.coords.latitude.toFixed(6), pos.coords.longitude.toFixed(6)); },
                function(){ doPost(type, null, null); },
                {enableHighAccuracy:true, timeout:8000}
            );
        } else {
            doPost(type, null, null);
        }
    }

    // Attach immediately — elements are already rendered above this script
    function wire(id, type){
        var el = document.getElementById(id);
        if (el) el.onclick = function(){ punch(type); };
    }

    wire('btn-punch-in',    'punch_in');
    wire('btn-lunch-start', 'lunch_start');
    wire('btn-lunch-end',   'lunch_end');
    wire('btn-punch-out',   'punch_out');
})();
</script>

<?php
$calDataJson = json_encode($calData);
$inlineJS = <<<JS
if (typeof AttendanceCalendar !== 'undefined') {
    AttendanceCalendar.init('att-calendar', {$calDataJson});
}
JS;
?>
<script src="<?= SITE_URL ?>/assets/js/calendar.js"></script>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>

