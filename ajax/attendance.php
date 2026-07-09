<?php
/**
 * AJAX — Attendance Punch Handler
 * Handles: punch_in, punch_out, lunch_start, lunch_end
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

startSession();
header('Content-Type: application/json');

// Must be student
if (!isStudent()) {
    jsonResponse(false, 'Unauthorized');
}

$pdo    = db();
$sId    = $_SESSION['student_id'];
$today  = date('Y-m-d');
$now    = date('H:i:s');
$action = $_POST['action'] ?? '';
$image  = $_POST['image']  ?? '';

// Validate CSRF
if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
    jsonResponse(false, 'Invalid security token.');
}

// Validate action
$valid = ['punch_in','punch_out','lunch_start','lunch_end'];
if (!in_array($action, $valid)) {
    jsonResponse(false, 'Invalid action.');
}

// Get or create today's attendance record
$att = $pdo->prepare("SELECT * FROM attendance WHERE student_id=? AND date=?");
$att->execute([$sId, $today]);
$att = $att->fetch();

// ─── Save Webcam Image ────────────────────────────────────────────────────────
function saveWebcamImage(string $base64, int $attId, string $type): string|false {
    if (!str_starts_with($base64, 'data:image/')) return false;
    $data = preg_replace('/^data:image\/\w+;base64,/', '', $base64);
    $data = base64_decode($data);
    if (!$data) return false;

    $filename = $type . '_' . date('Ymd_His') . '_' . $attId . '.jpg';
    $path     = UPLOAD_ATTENDANCE . '/' . $filename;
    if (file_put_contents($path, $data) !== false) {
        return $filename;
    }
    return false;
}

// ─── Punch In ─────────────────────────────────────────────────────────────────
if ($action === 'punch_in') {
    if ($att && $att['punch_in']) {
        jsonResponse(false, 'You have already punched in today at ' . formatTime($att['punch_in']));
    }

    // Check holiday
    $holiday = $pdo->prepare("SELECT id FROM holidays WHERE date=?");
    $holiday->execute([$today]);
    if ($holiday->fetchColumn()) {
        jsonResponse(false, 'Today is a holiday. Attendance not required.');
    }

    // Create/update attendance record
    if (!$att) {
        $pdo->prepare("INSERT INTO attendance (student_id,date,punch_in,status,punch_in_ip,punch_in_browser) VALUES (?,?,?,'Present',?,?)")
            ->execute([$sId, $today, $now, $_SERVER['REMOTE_ADDR'] ?? '', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]);
        $attId = $pdo->lastInsertId();
    } else {
        $pdo->prepare("UPDATE attendance SET punch_in=?, status='Present', punch_in_ip=?, punch_in_browser=? WHERE id=?")
            ->execute([$now, $_SERVER['REMOTE_ADDR'] ?? '', substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255), $att['id']]);
        $attId = $att['id'];
    }

    // Determine if late
    if (strtotime($now) > strtotime(LATE_AFTER)) {
        $pdo->prepare("UPDATE attendance SET status='Late' WHERE id=?")->execute([$attId]);
    }

    // Save image
    if ($image) {
        $imgFile = saveWebcamImage($image, $attId, 'punch_in');
        if ($imgFile) {
            $pdo->prepare("INSERT INTO attendance_images (attendance_id,type,image_path) VALUES (?,?,'punch_in')")
                ->execute([$attId, $imgFile]);
        }
    }

    // Location
    if (!empty($_POST['lat']) && !empty($_POST['lng'])) {
        $lat = round((float)$_POST['lat'], 6);
        $lng = round((float)$_POST['lng'], 6);
        $loc = 'Lat: ' . $lat . ', Lng: ' . $lng;
        $pdo->prepare("UPDATE attendance SET location=?, punch_in_lat=?, punch_in_lng=? WHERE id=?")->execute([$loc, $lat, $lng, $attId]);
    }

    logActivity('student', $sId, 'punch_in', "Punched in at $now");
    jsonResponse(true, 'Punched in successfully!', ['time' => date('h:i A')]);
}

// ─── Lunch Start ──────────────────────────────────────────────────────────────
if ($action === 'lunch_start') {
    if (!$att || !$att['punch_in']) {
        jsonResponse(false, 'Please punch in first.');
    }
    if ($att['lunch_start']) {
        jsonResponse(false, 'Lunch break already started at ' . formatTime($att['lunch_start']));
    }
    $pdo->prepare("UPDATE attendance SET lunch_start=? WHERE id=?")->execute([$now, $att['id']]);
    logActivity('student', $sId, 'lunch_start', "Lunch started at $now");
    jsonResponse(true, 'Lunch break started.', ['time' => date('h:i A')]);
}

// ─── Lunch End ────────────────────────────────────────────────────────────────
if ($action === 'lunch_end') {
    if (!$att || !$att['lunch_start']) {
        jsonResponse(false, 'Lunch break was not started.');
    }
    if ($att['lunch_end']) {
        jsonResponse(false, 'Lunch break already ended.');
    }
    $breakMins = (int)((strtotime($now) - strtotime($att['lunch_start'])) / 60);
    $pdo->prepare("UPDATE attendance SET lunch_end=?, break_duration=? WHERE id=?")->execute([$now, $breakMins, $att['id']]);
    logActivity('student', $sId, 'lunch_end', "Lunch ended at $now ($breakMins min break)");
    jsonResponse(true, "Lunch ended. Break duration: {$breakMins} minutes.", ['time' => date('h:i A')]);
}

// ─── Punch Out ────────────────────────────────────────────────────────────────
if ($action === 'punch_out') {
    if (!$att || !$att['punch_in']) {
        jsonResponse(false, 'Please punch in first.');
    }
    if ($att['punch_out']) {
        jsonResponse(false, 'Already punched out at ' . formatTime($att['punch_out']));
    }

    $workingHours = calculateWorkingHours($att['punch_in'], $now, $att['lunch_start'], $att['lunch_end']);
    $status       = determineAttendanceStatus($att['punch_in'], $workingHours);
    $overtime     = max(0, $workingHours - FULL_DAY_HOURS);

    $pdo->prepare("UPDATE attendance SET punch_out=?, working_hours=?, overtime_hours=?, status=?, punch_out_ip=? WHERE id=?")
        ->execute([$now, $workingHours, $overtime, $status, $_SERVER['REMOTE_ADDR'] ?? '', $att['id']]);

    // Location
    if (!empty($_POST['lat']) && !empty($_POST['lng'])) {
        $lat = round((float)$_POST['lat'], 6);
        $lng = round((float)$_POST['lng'], 6);
        $pdo->prepare("UPDATE attendance SET punch_out_lat=?, punch_out_lng=? WHERE id=?")->execute([$lat, $lng, $att['id']]);
    }

    // Save punch out image
    if ($image) {
        $imgFile = saveWebcamImage($image, $att['id'], 'punch_out');
        if ($imgFile) {
            $pdo->prepare("INSERT INTO attendance_images (attendance_id,type,image_path) VALUES (?,?,'punch_out')")
                ->execute([$att['id'], $imgFile]);
        }
    }

    logActivity('student', $sId, 'punch_out', "Punched out at $now. Hours: $workingHours. Status: $status");
    jsonResponse(true, "Punched out! Working hours: {$workingHours}h. Status: $status", [
        'time'   => date('h:i A'),
        'hours'  => $workingHours,
        'status' => $status
    ]);
}
