<?php
/**
 * Global Helper Functions
 * Student Internship Tracking & Attendance Management Portal
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// ─── Session Management ───────────────────────────────────────────────────────
function startSession(): void {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.use_strict_mode', 1);
        ini_set('session.cookie_samesite', 'Lax');
        session_name('INTERNTRACK_SID');
        session_start();
    }
}

function checkSessionTimeout(): void {
    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        header('Location: ' . SITE_URL . '/login.php?timeout=1');
        exit;
    }
    $_SESSION['last_activity'] = time();
}

// ─── CSRF ─────────────────────────────────────────────────────────────────────
function generateCsrfToken(): string {
    if (empty($_SESSION[CSRF_TOKEN_NAME])) {
        $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_NAME];
}

function validateCsrfToken(string $token): bool {
    return isset($_SESSION[CSRF_TOKEN_NAME]) && hash_equals($_SESSION[CSRF_TOKEN_NAME], $token);
}

function csrfField(): string {
    return '<input type="hidden" name="' . CSRF_TOKEN_NAME . '" value="' . generateCsrfToken() . '">';
}

// ─── Input Sanitisation ───────────────────────────────────────────────────────
function clean(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
}

function sanitizeEmail(string $email): string|false {
    return filter_var(trim($email), FILTER_SANITIZE_EMAIL);
}

function isValidEmail(string $email): bool {
    return (bool) filter_var($email, FILTER_VALIDATE_EMAIL);
}

// ─── Flash Messages ───────────────────────────────────────────────────────────
function setFlash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function getFlash(): array|null {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

// ─── Redirect ────────────────────────────────────────────────────────────────
function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

// ─── Date / Time Helpers ─────────────────────────────────────────────────────
function formatDate(string $date, string $format = 'd M Y'): string {
    return date($format, strtotime($date));
}

function formatDateTime(string $datetime, string $format = 'd M Y, h:i A'): string {
    return date($format, strtotime($datetime));
}

function formatTime(string $time): string {
    return date('h:i A', strtotime($time));
}

function timeAgo(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)        return 'Just now';
    if ($diff < 3600)      return floor($diff / 60) . ' min ago';
    if ($diff < 86400)     return floor($diff / 3600) . ' hr ago';
    if ($diff < 604800)    return floor($diff / 86400) . ' days ago';
    return formatDate($datetime);
}

function calculateWorkingHours(?string $punchIn, ?string $punchOut, ?string $lunchStart, ?string $lunchEnd): float {
    if (!$punchIn || !$punchOut) return 0.0;
    $in  = strtotime($punchIn);
    $out = strtotime($punchOut);
    $break = 0;
    if ($lunchStart && $lunchEnd) {
        $break = strtotime($lunchEnd) - strtotime($lunchStart);
    }
    $worked = max(0, ($out - $in - $break)) / 3600;
    return round($worked, 2);
}

// ─── Attendance Status Logic ──────────────────────────────────────────────────
function determineAttendanceStatus(string $punchIn, float $workingHours): string {
    $lateTime   = strtotime(LATE_AFTER);
    $punchTime  = strtotime($punchIn);
    if ($workingHours >= FULL_DAY_HOURS) {
        return $punchTime > $lateTime ? 'Late' : 'Present';
    }
    if ($workingHours >= HALF_DAY_HOURS) {
        return 'Half Day';
    }
    return 'Present';
}

// ─── Pagination ───────────────────────────────────────────────────────────────
function paginate(int $total, int $page, int $perPage = RECORDS_PER_PAGE): array {
    $totalPages = (int) ceil($total / $perPage);
    $page       = max(1, min($page, $totalPages));
    $offset     = ($page - 1) * $perPage;
    return [
        'total'       => $total,
        'per_page'    => $perPage,
        'current'     => $page,
        'total_pages' => $totalPages,
        'offset'      => $offset,
        'has_prev'    => $page > 1,
        'has_next'    => $page < $totalPages,
    ];
}

function renderPagination(array $pag, string $url): string {
    if ($pag['total_pages'] <= 1) return '';
    $html  = '<nav class="pagination">';
    $separator = str_contains($url, '?') ? '&' : '?';
    if ($pag['has_prev']) {
        $html .= '<a href="' . $url . $separator . 'page=' . ($pag['current'] - 1) . '" class="page-btn">&laquo; Prev</a>';
    }
    for ($i = max(1, $pag['current'] - 2); $i <= min($pag['total_pages'], $pag['current'] + 2); $i++) {
        $active = ($i === $pag['current']) ? ' active' : '';
        $html  .= '<a href="' . $url . $separator . 'page=' . $i . '" class="page-btn' . $active . '">' . $i . '</a>';
    }
    if ($pag['has_next']) {
        $html .= '<a href="' . $url . $separator . 'page=' . ($pag['current'] + 1) . '" class="page-btn">Next &raquo;</a>';
    }
    $html .= '</nav>';
    return $html;
}

// ─── Activity Logging ─────────────────────────────────────────────────────────
function logActivity(string $userType, int $userId, string $action, string $description = ''): void {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("INSERT INTO activity_logs (user_type,user_id,action,description,ip_address,user_agent) VALUES (?,?,?,?,?,?)");
        $stmt->execute([
            $userType, $userId, $action, $description,
            $_SERVER['REMOTE_ADDR'] ?? '',
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
        ]);
    } catch (Exception $e) {
        error_log('[LOG ERROR] ' . $e->getMessage());
    }
}

function logLogin(string $userType, int|null $userId, string $email, string $status): void {
    try {
        $pdo = db();
        $stmt = $pdo->prepare("INSERT INTO login_logs (user_type,user_id,email,status,ip_address,user_agent) VALUES (?,?,?,?,?,?)");
        $stmt->execute([
            $userType, $userId, $email, $status,
            $_SERVER['REMOTE_ADDR'] ?? '',
            substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)
        ]);
    } catch (Exception $e) {
        error_log('[LOGIN LOG ERROR] ' . $e->getMessage());
    }
}

// ─── Notification Helpers ─────────────────────────────────────────────────────
function createNotification(int $studentId, string $type, string $title, string $message, string $link = ''): void {
    try {
        $pdo  = db();
        $stmt = $pdo->prepare("INSERT INTO notifications (student_id,type,title,message,link) VALUES (?,?,?,?,?)");
        $stmt->execute([$studentId, $type, $title, $message, $link]);
    } catch (Exception $e) {
        error_log('[NOTIF ERROR] ' . $e->getMessage());
    }
}

function getUnreadNotificationCount(int $studentId): int {
    try {
        $pdo  = db();
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE student_id = ? AND is_read = 0");
        $stmt->execute([$studentId]);
        return (int) $stmt->fetchColumn();
    } catch (Exception $e) {
        return 0;
    }
}

// ─── Attendance Percentage ─────────────────────────────────────────────────────
function getAttendancePercentage(int $studentId): float {
    try {
        $pdo  = db();
        // Working days = all non-holiday weekdays since internship start
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as present_days
            FROM attendance
            WHERE student_id = ? AND status IN ('Present','Late','Half Day')
        ");
        $stmt->execute([$studentId]);
        $present = (int) $stmt->fetchColumn();

        $stmtTotal = $pdo->prepare("
            SELECT COUNT(*) FROM attendance WHERE student_id = ?
        ");
        $stmtTotal->execute([$studentId]);
        $total = (int) $stmtTotal->fetchColumn();

        if ($total === 0) return 0.0;
        return round(($present / $total) * 100, 1);
    } catch (Exception $e) {
        return 0.0;
    }
}

// ─── Task Completion % ────────────────────────────────────────────────────────
function getTaskCompletionPercentage(int $studentId): float {
    try {
        $pdo  = db();
        $stmt = $pdo->prepare("SELECT COUNT(*) as total, SUM(status IN ('Completed','Verified')) as done FROM tasks WHERE student_id = ?");
        $stmt->execute([$studentId]);
        $row  = $stmt->fetch();
        if (!$row['total']) return 0.0;
        return round(($row['done'] / $row['total']) * 100, 1);
    } catch (Exception $e) {
        return 0.0;
    }
}

// ─── Certificate Number Generator ────────────────────────────────────────────
function generateCertNumber(int $studentId): string {
    return 'CERT-' . date('Y') . '-' . str_pad($studentId, 5, '0', STR_PAD_LEFT) . '-' . strtoupper(substr(md5(uniqid()), 0, 6));
}

// ─── File Size Formatter ──────────────────────────────────────────────────────
function formatFileSize(int $bytes): string {
    if ($bytes < 1024)       return $bytes . ' B';
    if ($bytes < 1048576)    return round($bytes / 1024, 1) . ' KB';
    return round($bytes / 1048576, 1) . ' MB';
}

// ─── Badge HTML ───────────────────────────────────────────────────────────────
function statusBadge(string $status): string {
    $map = [
        'Present'     => 'success',
        'Approved'    => 'success',
        'Completed'   => 'success',
        'Verified'    => 'success',
        'Active'      => 'success',
        'Absent'      => 'danger',
        'Rejected'    => 'danger',
        'Cancelled'   => 'danger',
        'Late'        => 'warning',
        'Pending'     => 'warning',
        'In Progress' => 'info',
        'Half Day'    => 'orange',
        'Leave'       => 'purple',
        'Holiday'     => 'blue',
        'Planning'    => 'secondary',
        'On Hold'     => 'secondary',
    ];
    $class = $map[$status] ?? 'secondary';
    return '<span class="badge badge-' . $class . '">' . htmlspecialchars($status) . '</span>';
}

// ─── Get Current Student / Admin ─────────────────────────────────────────────
function getCurrentStudent(): array|false {
    if (!isset($_SESSION['student_id'])) return false;
    try {
        $pdo  = db();
        $stmt = $pdo->prepare("SELECT s.*, d.name as dept_name FROM students s LEFT JOIN departments d ON s.department_id=d.id WHERE s.id = ?");
        $stmt->execute([$_SESSION['student_id']]);
        return $stmt->fetch() ?: false;
    } catch (Exception $e) {
        return false;
    }
}

function getCurrentAdmin(): array|false {
    if (!isset($_SESSION['admin_id'])) return false;
    try {
        $pdo  = db();
        $stmt = $pdo->prepare("SELECT * FROM admin WHERE id = ?");
        $stmt->execute([$_SESSION['admin_id']]);
        return $stmt->fetch() ?: false;
    } catch (Exception $e) {
        return false;
    }
}

// ─── Profile Photo URL ────────────────────────────────────────────────────────
function profilePhotoUrl(?string $path, string $role = 'student'): string {
    if ($path && file_exists(BASE_PATH . '/uploads/profile/' . $path)) {
        return SITE_URL . '/uploads/profile/' . $path;
    }
    return SITE_URL . '/assets/images/avatar-' . $role . '.png';
}

// ─── JSON Response (for AJAX) ────────────────────────────────────────────────
function jsonResponse(bool $success, string $message, array $data = []): never {
    header('Content-Type: application/json');
    echo json_encode(array_merge(['success' => $success, 'message' => $message], $data));
    exit;
}
