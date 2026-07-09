<?php
/**
 * Admin — Edit Attendance Record
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

requireAdmin();
$pdo = db();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    setFlash('error', 'Attendance record not specified.');
    redirect(SITE_URL . '/admin/attendance.php');
}

$stmt = $pdo->prepare("
    SELECT a.*, s.name as student_name, s.student_id as sid, s.profile_photo
    FROM attendance a
    JOIN students s ON a.student_id = s.id
    WHERE a.id = ?
");
$stmt->execute([$id]);
$record = $stmt->fetch();

if (!$record) {
    setFlash('error', 'Attendance record not found.');
    redirect(SITE_URL . '/admin/attendance.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $punch_in    = $_POST['punch_in']   ?? null;
        $lunch_start  = $_POST['lunch_start'] ?? null;
        $lunch_end   = $_POST['lunch_end']  ?? null;
        $punch_out   = $_POST['punch_out']  ?? null;
        $status      = in_array($_POST['status']??'',['Present','Absent','Late','Half Day','Leave','Holiday']) ? $_POST['status'] : 'Present';
        $remarks     = clean($_POST['remarks'] ?? '');

        if (empty($errors)) {
            // Recalculate hours if values changed
            $working_hours = calculateWorkingHours($punch_in, $punch_out, $lunch_start, $lunch_end);
            $overtime      = max(0.0, $working_hours - FULL_DAY_HOURS);

            $stmt = $pdo->prepare("
                UPDATE attendance 
                SET punch_in=?, lunch_start=?, lunch_end=?, punch_out=?, working_hours=?, overtime_hours=?, status=?, remarks=?, is_corrected=1, approved_by=?
                WHERE id=?
            ");
            $stmt->execute([
                $punch_in ?: null, $lunch_start ?: null, $lunch_end ?: null, $punch_out ?: null,
                $working_hours, $overtime, $status, $remarks, $_SESSION['admin_id'], $id
            ]);

            logActivity('admin', $_SESSION['admin_id'], 'edit_attendance', "Edited attendance record ID: $id for student ID: {$record['student_id']}");
            setFlash('success', 'Attendance record corrected successfully!');
            redirect(SITE_URL . '/admin/attendance.php?date=' . $record['date']);
        }
    }
}

$pageTitle  = 'Edit Attendance';
$userRole   = 'admin';
$breadcrumb = [['label'=>'Attendance','url'=>SITE_URL.'/admin/attendance.php'],['label'=>'Edit Record']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-admin.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">Correct Attendance</h1>
            <p class="page-subtitle">Adjust timestamp records for: <?= htmlspecialchars($record['student_name']) ?> (<?= $record['date'] ?>)</p>
        </div>
        <a href="<?= SITE_URL ?>/admin/attendance.php?date=<?= $record['date'] ?>" class="btn btn-secondary">
            <i class="fa fa-arrow-left"></i> Back
        </a>
    </div>

    <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <div class="card" style="max-width: 600px; margin: 0 auto">
        <div class="card-header"><span class="card-title"><i class="fa fa-clock"></i> Timestamps Setup</span></div>
        <div class="card-body">
            <form method="POST">
                <?= csrfField() ?>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Punch In Time</label>
                        <input type="time" name="punch_in" class="form-control" value="<?= $record['punch_in'] ? date('H:i', strtotime($record['punch_in'])) : '' ?>">
                        <?php if (!empty($record['punch_in_lat']) && !empty($record['punch_in_lng'])): ?>
                            <div class="text-xs text-muted" style="margin-top:6px;font-size:0.75rem">
                                <a href="https://www.google.com/maps?q=<?= $record['punch_in_lat'] ?>,<?= $record['punch_in_lng'] ?>" 
                                   target="_blank" style="display:inline-flex;align-items:center;gap:3px;color:var(--text-muted)">
                                    <i class="fa fa-map-marker-alt" style="color:var(--c-danger)"></i> GPS: <?= $record['punch_in_lat'] ?>, <?= $record['punch_in_lng'] ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Punch Out Time</label>
                        <input type="time" name="punch_out" class="form-control" value="<?= $record['punch_out'] ? date('H:i', strtotime($record['punch_out'])) : '' ?>">
                        <?php if (!empty($record['punch_out_lat']) && !empty($record['punch_out_lng'])): ?>
                            <div class="text-xs text-muted" style="margin-top:6px;font-size:0.75rem">
                                <a href="https://www.google.com/maps?q=<?= $record['punch_out_lat'] ?>,<?= $record['punch_out_lng'] ?>" 
                                   target="_blank" style="display:inline-flex;align-items:center;gap:3px;color:var(--text-muted)">
                                    <i class="fa fa-map-marker-alt" style="color:var(--c-danger)"></i> GPS: <?= $record['punch_out_lat'] ?>, <?= $record['punch_out_lng'] ?>
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Lunch Start</label>
                        <input type="time" name="lunch_start" class="form-control" value="<?= $record['lunch_start'] ? date('H:i', strtotime($record['lunch_start'])) : '' ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Lunch End</label>
                        <input type="time" name="lunch_end" class="form-control" value="<?= $record['lunch_end'] ? date('H:i', strtotime($record['lunch_end'])) : '' ?>">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Attendance Status</label>
                    <select name="status" class="form-control">
                        <?php foreach (['Present','Absent','Late','Half Day','Leave','Holiday'] as $st): ?>
                        <option value="<?= $st ?>" <?= $record['status']===$st?'selected':'' ?>><?= $st ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label class="form-label">Correction Reason / Remarks</label>
                    <textarea name="remarks" class="form-control" rows="3" placeholder="Specify why this correction was made…"><?= htmlspecialchars($record['remarks'] ?? '') ?></textarea>
                </div>

                <button type="submit" class="btn btn-primary w-100 mt-16">
                    <i class="fa fa-save"></i> Save Corrections
                </button>
            </form>
        </div>
    </div>

</main>
</div>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
