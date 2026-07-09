<?php
/**
 * Global Configuration File
 * Student Internship Tracking & Attendance Management Portal
 */

// ─── Database Settings ────────────────────────────────────────────────────────
define('DB_HOST',     'localhost');
define('DB_NAME',     'internship_portal');
define('DB_USER',     'root');
define('DB_PASS',     '');
define('DB_CHARSET',  'utf8mb4');

// ─── Site Settings ────────────────────────────────────────────────────────────
define('SITE_NAME',   'InternTrack Pro');
define('SITE_URL',    'http://localhost/INTERNSHIP_PORTAL_NEW');
if (!defined('BASE_PATH')) {
    define('BASE_PATH',   dirname(__DIR__));          // /INTERNSHIP_PORTAL_NEW
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
define('OFFICE_START',      '09:00:00');   // Punch-in window opens
define('LATE_AFTER',        '09:30:00');   // Marked late after this time
define('HALF_DAY_HOURS',    4.0);          // Hours for half-day
define('FULL_DAY_HOURS',    8.0);          // Standard working hours
define('LUNCH_DURATION',    60);           // Default lunch in minutes
define('OFFICE_END',        '18:00:00');   // Expected punch-out

// ─── Session / Security ───────────────────────────────────────────────────────
define('SESSION_TIMEOUT',   1800);         // 30 minutes
define('REMEMBER_ME_DAYS',  30);
define('MAX_LOGIN_ATTEMPTS',5);
define('CSRF_TOKEN_NAME',   '_csrf_token');

// ─── File Upload Limits ───────────────────────────────────────────────────────
define('MAX_FILE_SIZE',     5 * 1024 * 1024); // 5 MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg','image/png','image/gif','image/webp']);
define('ALLOWED_DOC_TYPES',   ['application/pdf',
                                'application/msword',
                                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                                'application/vnd.ms-powerpoint',
                                'application/vnd.openxmlformats-officedocument.presentationml.presentation',
                                'image/jpeg','image/png']);

// ─── Pagination ───────────────────────────────────────────────────────────────
define('RECORDS_PER_PAGE', 15);

// ─── Timezone ─────────────────────────────────────────────────────────────────
date_default_timezone_set('Asia/Kolkata');

// ─── Environment ─────────────────────────────────────────────────────────────
define('APP_ENV', 'development');   // 'production' in live

if (APP_ENV === 'development') {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}
