<?php
/**
 * Logout
 */
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/functions.php';

startSession();

// Log before destroying
if (isset($_SESSION['admin_id'])) {
    logActivity('admin', $_SESSION['admin_id'], 'logout', 'Admin logged out');
} elseif (isset($_SESSION['student_id'])) {
    logActivity('student', $_SESSION['student_id'], 'logout', 'Student logged out');
    // Clear remember token
    if (isset($_COOKIE['remember_token'])) {
        try {
            db()->prepare("UPDATE students SET remember_token=NULL WHERE id=?")->execute([$_SESSION['student_id']]);
        } catch (Exception $e) {}
        setcookie('remember_token', '', time() - 3600, '/');
    }
}

session_unset();
session_destroy();

redirect(SITE_URL . '/login.php');
