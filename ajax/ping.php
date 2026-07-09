<?php
/**
 * AJAX Ping — extends session
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/includes/functions.php';

startSession();
$_SESSION['last_activity'] = time();
header('Content-Type: application/json');
echo json_encode(['ok' => true]);
