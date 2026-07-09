<?php
/**
 * Global Configuration File
 * Student Internship Tracking & Attendance Management Portal
 *
 * ── URL Strategy ─────────────────────────────────────────────────────────────
 * SITE_URL is dynamically detected from the request so this code works on:
 *   • Local XAMPP   → http://localhost/INTERNSHIP_PORTAL_NEW
 *   • Docker local  → http://localhost:8080
 *   • Render        → https://internship-portal-new.onrender.com
 *   • Any future domain — zero code changes needed.
 *
 * Priority: environment variable SITE_URL > auto-detection
 */

// ─── Database Settings ────────────────────────────────────────────────────────
define('DB_HOST',    getenv('DB_HOST') ?: 'localhost');
define('DB_PORT',    getenv('DB_PORT') ?: '3306');
define('DB_NAME',    getenv('DB_NAME') ?: 'internship_portal');
define('DB_USER',    getenv('DB_USER') ?: 'root');
define('DB_PASS',    getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// ─── Site URL — auto-detect, never hardcode ───────────────────────────────────
if (!defined('SITE_URL')) {
    $envUrl = getenv('SITE_URL');
    if ($envUrl) {
        // Explicit environment variable (Docker / Render / production) — highest priority
        define('SITE_URL', rtrim($envUrl, '/'));
    } else {
        // ── Scheme & host ──────────────────────────────────────────────────────
        $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
                || (($_SERVER['SERVER_PORT'] ?? 80) == 443);
        $scheme = $isHttps ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';

        // ── Subpath of the project root ────────────────────────────────────────
        // Use SCRIPT_NAME as the primary source — Apache always supplies this with
        // correct casing and forward slashes on ALL platforms (Windows, Linux, Docker).
        //
        // Examples:
        //   XAMPP local  → SCRIPT_NAME = /INTERNSHIP_PORTAL_NEW/login.php
        //   Docker/Render → SCRIPT_NAME = /login.php  (copied to docroot)
        //   Admin page   → SCRIPT_NAME = /INTERNSHIP_PORTAL_NEW/admin/dashboard.php
        //
        // Known internal directories that are NOT part of the project URL prefix:
        $internalDirs = ['admin', 'student', 'ajax', 'includes', 'assets', 'config',
                         'database', 'helpers', 'uploads', 'docker'];

        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '/index.php';
        $parts      = explode('/', ltrim($scriptName, '/'));

        // Collect path segments that precede any internal directory (= the project prefix)
        $pathParts = [];
        foreach ($parts as $i => $part) {
            if ($i === count($parts) - 1) break;        // stop at the filename
            if (in_array(strtolower($part), $internalDirs, true)) break;
            $pathParts[] = $part;
        }
        $subdir = $pathParts ? ('/' . implode('/', $pathParts)) : '';

        define('SITE_URL', $scheme . '://' . $host . $subdir);
    }
}




// ─── File System Root ─────────────────────────────────────────────────────────
if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__));
}

// ─── Upload Paths ─────────────────────────────────────────────────────────────
define('UPLOAD_PATH',          BASE_PATH . '/uploads');
define('UPLOAD_PROFILE',       UPLOAD_PATH . '/profile');
define('UPLOAD_ATTENDANCE',    UPLOAD_PATH . '/attendance');
define('UPLOAD_DOCUMENTS',     UPLOAD_PATH . '/documents');
define('UPLOAD_REPORTS',       UPLOAD_PATH . '/reports');
define('UPLOAD_LEAVES',        UPLOAD_PATH . '/leaves');
define('UPLOAD_ANNOUNCEMENTS', UPLOAD_PATH . '/announcements');

// ─── Office Settings ─────────────────────────────────────────────────────────
define('OFFICE_START',   '09:00:00');
define('LATE_AFTER',     '09:30:00');
define('HALF_DAY_HOURS', 4.0);
define('FULL_DAY_HOURS', 8.0);
define('LUNCH_DURATION', 60);
define('OFFICE_END',     '18:00:00');

// ─── Session / Security ───────────────────────────────────────────────────────
define('SESSION_TIMEOUT',    1800);
define('REMEMBER_ME_DAYS',   30);
define('MAX_LOGIN_ATTEMPTS', 5);
define('CSRF_TOKEN_NAME',    '_csrf_token');

// ─── File Upload Limits ───────────────────────────────────────────────────────
define('MAX_FILE_SIZE', 5 * 1024 * 1024); // 5 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);
define('ALLOWED_DOC_TYPES', [
    'application/pdf',
    'application/msword',
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'image/jpeg',
    'image/png',
]);

// ─── Pagination ───────────────────────────────────────────────────────────────
define('RECORDS_PER_PAGE', 15);

// ─── Timezone ─────────────────────────────────────────────────────────────────
date_default_timezone_set('Asia/Kolkata');

// ─── Environment ─────────────────────────────────────────────────────────────
$_appEnv = getenv('APP_ENV') ?: 'development';
define('APP_ENV', $_appEnv);
unset($_appEnv);

if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
