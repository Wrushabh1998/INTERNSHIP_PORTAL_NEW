<?php
/**
 * Authentication Middleware
 * Call requireAdmin() or requireStudent() at the top of each protected page
 */

require_once __DIR__ . '/functions.php';

function requireAdmin(): void {
    startSession();
    checkSessionTimeout();
    if (!isset($_SESSION['admin_id']) || $_SESSION['user_role'] !== 'admin') {
        setFlash('error', 'Please login to access the admin panel.');
        redirect(SITE_URL . '/login.php');
    }
}

function requireStudent(): void {
    startSession();
    checkSessionTimeout();
    if (!isset($_SESSION['student_id']) || $_SESSION['user_role'] !== 'student') {
        setFlash('error', 'Please login to access the student portal.');
        redirect(SITE_URL . '/login.php');
    }
}

function isLoggedIn(): bool {
    startSession();
    return isset($_SESSION['admin_id']) || isset($_SESSION['student_id']);
}

function isAdmin(): bool {
    return isset($_SESSION['admin_id']) && $_SESSION['user_role'] === 'admin';
}

function isStudent(): bool {
    return isset($_SESSION['student_id']) && $_SESSION['user_role'] === 'student';
}
