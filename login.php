<?php
/**
 * Login Page — Dual Role (Admin / Student)
 * InternTrack Pro
 */

define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

startSession();

// Redirect if already logged in
if (isLoggedIn()) {
    redirect(SITE_URL . '/' . ($_SESSION['user_role'] ?? 'student') . '/dashboard.php');
}

$error   = '';
$success = '';
$role    = 'student';

// ─── Handle POST ──────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // CSRF Check
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Invalid form submission. Please refresh and try again.';
    } else {
        $role     = in_array($_POST['role'] ?? '', ['admin','student']) ? $_POST['role'] : 'student';
        $username = clean($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);

        if (!$username || !$password) {
            $error = 'Please enter your username/email and password.';
        } else {
            $pdo = db();

            if ($role === 'admin') {
                // ── Admin Login ──
                $stmt = $pdo->prepare("SELECT * FROM admin WHERE email = ? AND is_active = 1");
                $stmt->execute([$username]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    // Success
                    session_regenerate_id(true);
                    $_SESSION['admin_id']  = $user['id'];
                    $_SESSION['user_role'] = 'admin';
                    $_SESSION['last_activity'] = time();

                    // Update last login
                    $pdo->prepare("UPDATE admin SET last_login=NOW() WHERE id=?")->execute([$user['id']]);
                    logLogin('admin', $user['id'], $username, 'Success');
                    logActivity('admin', $user['id'], 'login', 'Admin logged in from ' . ($_SERVER['REMOTE_ADDR'] ?? ''));

                    redirect(SITE_URL . '/admin/dashboard.php');
                } else {
                    $error = 'Invalid admin credentials.';
                    logLogin('admin', null, $username, 'Failed');
                }

            } else {
                // ── Student Login ──
                $stmt = $pdo->prepare("SELECT * FROM students WHERE (student_id = ? OR email = ?) AND is_active = 1");
                $stmt->execute([$username, $username]);
                $user = $stmt->fetch();

                if ($user) {
                    // Check lockout
                    if ($user['locked_until'] && strtotime($user['locked_until']) > time()) {
                        $mins = ceil((strtotime($user['locked_until']) - time()) / 60);
                        $error = "Account locked. Try again in {$mins} minute(s).";
                    } elseif (password_verify($password, $user['password'])) {
                        // Success — reset attempts
                        session_regenerate_id(true);
                        $_SESSION['student_id']  = $user['id'];
                        $_SESSION['user_role']   = 'student';
                        $_SESSION['last_activity'] = time();

                        $pdo->prepare("UPDATE students SET last_login=NOW(), login_attempts=0, locked_until=NULL WHERE id=?")->execute([$user['id']]);

                        // Remember Me
                        if ($remember) {
                            $token = bin2hex(random_bytes(32));
                            $pdo->prepare("UPDATE students SET remember_token=? WHERE id=?")->execute([$token, $user['id']]);
                            setcookie('remember_token', $token, time() + (REMEMBER_ME_DAYS * 86400), '/', '', false, true);
                        }

                        logLogin('student', $user['id'], $username, 'Success');
                        logActivity('student', $user['id'], 'login', 'Student logged in');

                        redirect(SITE_URL . '/student/dashboard.php');
                    } else {
                        // Increment attempts
                        $attempts = $user['login_attempts'] + 1;
                        $lock = $attempts >= MAX_LOGIN_ATTEMPTS
                            ? date('Y-m-d H:i:s', strtotime('+15 minutes'))
                            : null;
                        $pdo->prepare("UPDATE students SET login_attempts=?, locked_until=? WHERE id=?")
                            ->execute([$attempts, $lock, $user['id']]);
                        $remaining = MAX_LOGIN_ATTEMPTS - $attempts;
                        $error = $lock
                            ? 'Too many failed attempts. Account locked for 15 minutes.'
                            : "Invalid credentials. {$remaining} attempt(s) remaining.";
                        logLogin('student', $user['id'], $username, 'Failed');
                    }
                } else {
                    $error = 'No account found with that email/registration number.';
                    logLogin('student', null, $username, 'Failed');
                }
            }
        }
    }
}

// Check remember me cookie
if (!isLoggedIn() && isset($_COOKIE['remember_token'])) {
    $pdo  = db();
    $stmt = $pdo->prepare("SELECT * FROM students WHERE remember_token = ? AND is_active = 1");
    $stmt->execute([$_COOKIE['remember_token']]);
    $user = $stmt->fetch();
    if ($user) {
        session_regenerate_id(true);
        $_SESSION['student_id']  = $user['id'];
        $_SESSION['user_role']   = 'student';
        $_SESSION['last_activity'] = time();
        redirect(SITE_URL . '/student/dashboard.php');
    }
}

$csrfToken = generateCsrfToken();
$timeout   = isset($_GET['timeout']) && $_GET['timeout'] === '1';
$flash     = getFlash();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Login — InternTrack Pro</title>
<meta name="description" content="Login to InternTrack Pro — Student Internship Tracking Portal">
<link rel="icon" href="<?= SITE_URL ?>/assets/images/favicon.svg" type="image/svg+xml">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&family=Outfit:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/style.css">
<link rel="stylesheet" href="<?= SITE_URL ?>/assets/css/login.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
</head>
<body style="position:relative">

<!-- Theme toggle -->
<button class="login-theme-toggle" data-theme-toggle aria-label="Toggle dark mode">
    <span class="theme-icon">🌙</span> <span>Dark Mode</span>
</button>

<div class="login-page">
    <!-- Left Branding Panel -->
    <div class="login-left">
        <div class="login-glow login-glow-1"></div>
        <div class="login-glow login-glow-2"></div>
        <div class="login-glow login-glow-3"></div>

        <div class="login-brand">
            <div class="brand-logo">🎓</div>
            <h1>InternTrack Pro</h1>
            <p>Student Internship Tracking &amp; Attendance Portal</p>
        </div>

        <div class="login-features">
            <div class="feature-item">
                <div class="feature-icon purple"><i class="fa fa-fingerprint"></i></div>
                <div class="feature-text">
                    <strong>Face Biometric Attendance</strong>
                    <span>Webcam-based punch in/out with photo capture</span>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon cyan"><i class="fa fa-chart-line"></i></div>
                <div class="feature-text">
                    <strong>Real-time Analytics</strong>
                    <span>Attendance, performance &amp; productivity insights</span>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon green"><i class="fa fa-tasks"></i></div>
                <div class="feature-text">
                    <strong>Task &amp; Project Tracking</strong>
                    <span>Track progress, deadlines &amp; milestones</span>
                </div>
            </div>
            <div class="feature-item">
                <div class="feature-icon orange"><i class="fa fa-certificate"></i></div>
                <div class="feature-text">
                    <strong>Auto Certificate Generation</strong>
                    <span>Download completion &amp; experience letters</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Right Form Panel -->
    <div class="login-right">
        <div class="login-form-header">
            <h2>Welcome Back 👋</h2>
            <p>Sign in to access your internship portal</p>
        </div>

        <!-- Flash messages -->
        <?php if ($timeout): ?>
        <div class="alert alert-warning">⏱️ Your session expired due to inactivity. Please login again.</div>
        <?php endif; ?>
        <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] === 'error' ? 'danger' : $flash['type'] ?>"><?= htmlspecialchars($flash['message']) ?></div>
        <?php endif; ?>

        <!-- Role Toggle -->
        <div class="role-tabs" id="role-tabs" role="tablist">
            <button class="role-tab <?= ($role === 'student') ? 'active' : '' ?>" data-role="student" type="button">
                <i class="fa fa-user-graduate"></i> Student
            </button>
            <button class="role-tab <?= ($role === 'admin') ? 'active' : '' ?>" data-role="admin" type="button">
                <i class="fa fa-user-shield"></i> Admin
            </button>
        </div>

        <!-- Login Form -->
        <form method="POST" id="login-form" novalidate>
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">
            <input type="hidden" name="role" id="role-field" value="<?= htmlspecialchars($role) ?>">

            <?php if ($error): ?>
            <div class="alert alert-danger" data-auto-dismiss="6000">
                <i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <div class="input-group">
                <span class="input-icon"><i class="fa fa-user"></i></span>
                <input type="text" name="email" id="email" class="form-control"
                       placeholder="Enter Email or Registration Number" autocomplete="username"
                       value="<?= htmlspecialchars($_POST['email'] ?? '') ?>" required>
            </div>

            <div class="input-group">
                <span class="input-icon"><i class="fa fa-lock"></i></span>
                <input type="password" name="password" id="password" class="form-control"
                       placeholder="Enter your password" autocomplete="current-password" required>
                <button type="button" class="input-toggle" onclick="togglePassword()" title="Show/hide password">
                    <i class="fa fa-eye" id="pw-icon"></i>
                </button>
            </div>

            <div class="form-options">
                <label class="checkbox-label">
                    <input type="checkbox" name="remember" <?= isset($_POST['remember']) ? 'checked' : '' ?>>
                    Remember me
                </label>
                <a href="<?= SITE_URL ?>/forgot-password.php" class="forgot-link">Forgot password?</a>
            </div>

            <button type="submit" class="btn btn-primary btn-login" id="login-btn">
                <i class="fa fa-sign-in-alt"></i> Sign In
            </button>
        </form>

        <!-- Demo Credentials -->
        <div class="demo-credentials">
            <h4>🔑 Demo Credentials</h4>
            <div class="demo-row">
                <span class="demo-role">Admin</span>
                <span class="demo-cred">admin@portal.com / Admin@123</span>
                <button class="demo-fill-btn" onclick="fillDemo('admin@portal.com','Admin@123','admin')">Fill</button>
            </div>
            <div class="demo-row">
                <span class="demo-role" style="background:var(--grad-success)">Student</span>
                <span class="demo-cred">aarav@student.com / Student@123</span>
                <button class="demo-fill-btn" onclick="fillDemo('aarav@student.com','Student@123','student')">Fill</button>
            </div>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="toast-container"></div>

<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
<script>
// Role toggle
document.querySelectorAll('.role-tab').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.role-tab').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        document.getElementById('role-field').value = this.dataset.role;
    });
});

// Password toggle
function togglePassword() {
    const pw   = document.getElementById('password');
    const icon = document.getElementById('pw-icon');
    if (pw.type === 'password') { pw.type = 'text'; icon.className = 'fa fa-eye-slash'; }
    else { pw.type = 'password'; icon.className = 'fa fa-eye'; }
}

// Demo fill
function fillDemo(email, pass, role) {
    document.getElementById('email').value    = email;
    document.getElementById('password').value = pass;
    document.getElementById('role-field').value = role;
    document.querySelectorAll('.role-tab').forEach(b => {
        b.classList.toggle('active', b.dataset.role === role);
    });
}

// Prevent double submit
document.getElementById('login-form').addEventListener('submit', function () {
    const btn = document.getElementById('login-btn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner spinner-sm"></span> Signing in…';
});
</script>
</body>
</html>
