<?php
/**
 * Index — redirect to login or dashboard
 */
define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

startSession();

if (isAdmin()) {
    redirect(SITE_URL . '/admin/dashboard.php');
} elseif (isStudent()) {
    redirect(SITE_URL . '/student/dashboard.php');
} else {
    redirect(SITE_URL . '/login.php');
}
