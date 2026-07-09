<?php
/**
 * Dual Role — Forgot & Reset Password
 * InternTrack Pro
 */

define('BASE_PATH', __DIR__);
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';

startSession();
$pdo = db();

$error = '';
$success = '';
$simulatedLink = '';

$token = clean($_GET['token'] ?? '');

// ─── STEP 1: Handle Email Submission (Request Link) ──────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'request') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $email = sanitizeEmail($_POST['email'] ?? '');
        if (!$email || !isValidEmail($email)) {
            $error = 'Please enter a valid email address.';
        } else {
            // Find student or admin
            $stmt = $pdo->prepare("SELECT id, name FROM students WHERE email=? AND is_active=1");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if ($user) {
                $resetToken = bin2hex(random_bytes(20));
                $expires    = date('Y-m-d H:i:s', strtotime('+1 hour'));

                $pdo->prepare("UPDATE students SET reset_token=?, reset_expires=? WHERE id=?")
                    ->execute([$resetToken, $expires, $user['id']]);

                $success = 'A password reset link has been simulated. See development options below.';
                $simulatedLink = SITE_URL . '/forgot-password.php?token=' . $resetToken;
                logActivity('student', $user['id'], 'forgot_password_request', "Requested password reset");
            } else {
                // To prevent email enumeration, we show a generic success message or alert error. Let's make it easy for developer.
                $error = 'No active student found with that email.';
            }
        }
    }
}

// ─── STEP 2: Handle Password Reset Form (With Token) ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $error = 'Invalid security token.';
    } else {
        $token     = clean($_POST['token'] ?? '');
        $newPw     = $_POST['new_pw']     ?? '';
        $confirmPw = $_POST['confirm_pw'] ?? '';

        if (!$token) {
            $error = 'Reset token is missing.';
        } elseif (!$newPw || !$confirmPw) {
            $error = 'Please fill in all password fields.';
        } elseif ($newPw !== $confirmPw) {
            $error = 'Passwords do not match.';
        } elseif (strlen($newPw) < 6) {
            $error = 'Password must be at least 6 characters.';
        } else {
            // Validate token
            $stmt = $pdo->prepare("SELECT id, name, reset_expires FROM students WHERE reset_token = ?");
            $stmt->execute([$token]);
            $student = $stmt->fetch();

            if ($student && strtotime($student['reset_expires']) > time()) {
                $hash = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
                $pdo->prepare("UPDATE students SET password=?, reset_token=NULL, reset_expires=NULL WHERE id=?")
                    ->execute([$hash, $student['id']]);
                
                logActivity('student', $student['id'], 'forgot_password_reset', "Reset password using reset token");
                setFlash('success', 'Your password has been reset successfully. Please log in.');
                redirect(SITE_URL . '/login.php');
            } else {
                $error = 'Reset token is invalid or has expired.';
            }
        }
    }
}

// Check if token exists and is valid (to show reset form or show forgot form)
$showResetForm = false;
if ($token) {
    $stmt = $pdo->prepare("SELECT id, name, reset_expires FROM students WHERE reset_token = ?");
    $stmt->execute([$token]);
    $student = $stmt->fetch();
    if ($student && strtotime($student['reset_expires']) > time()) {
        $showResetForm = true;
    } else {
        $error = 'Reset token is invalid or has expired.';
    }
}

$csrfToken = generateCsrfToken();
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Reset Password — InternTrack Pro</title>
<link rel="icon" href="<?= SITE_URL ?>/assets/images/favicon.svg" type="image/svg+xml">
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
            <p>Recover your account password</p>
        </div>
    </div>

    <!-- Right Form Panel -->
    <div class="login-right">
        <div class="login-form-header">
            <h2>Reset Password</h2>
            <p><?= $showResetForm ? 'Enter your new account password' : 'Enter your email to request recovery' ?></p>
        </div>

        <?php if ($error): ?>
        <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
        <div class="alert alert-success"><i class="fa fa-check-circle"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($simulatedLink): ?>
        <div class="alert alert-info" style="font-size:0.8rem">
            ⚙️ <strong>Development Sandbox Mode:</strong><br>
            A password reset email would be triggered in production. Use this simulated local link to reset password:<br>
            <a href="<?= $simulatedLink ?>" style="font-weight:700; text-decoration:underline"><?= $simulatedLink ?></a>
        </div>
        <?php endif; ?>

        <?php if ($showResetForm): ?>
        <!-- Reset Password Form -->
        <form method="POST">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="reset">
            <input type="hidden" name="token" value="<?= htmlspecialchars($token) ?>">

            <div class="form-group">
                <label class="form-label">New Password</label>
                <input type="password" name="new_pw" class="form-control" placeholder="••••••••" required minlength="6">
            </div>

            <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <input type="password" name="confirm_pw" class="form-control" placeholder="••••••••" required minlength="6">
            </div>

            <button type="submit" class="btn btn-primary w-100 mt-12"><i class="fa fa-save"></i> Save New Password</button>
        </form>
        <?php else: ?>
        <!-- Request Reset Form -->
        <form method="POST">
            <input type="hidden" name="<?= CSRF_TOKEN_NAME ?>" value="<?= $csrfToken ?>">
            <input type="hidden" name="action" value="request">

            <div class="form-group">
                <label class="form-label">Account Email Address</label>
                <input type="email" name="email" class="form-control" placeholder="e.g. aarav@student.com" required>
            </div>

            <button type="submit" class="btn btn-primary w-100 mt-12"><i class="fa fa-paper-plane"></i> Send Recovery Link</button>
        </form>
        <?php endif; ?>

        <hr class="divider">
        <div class="text-center">
            <a href="<?= SITE_URL ?>/login.php" class="text-sm fw-600"><i class="fa fa-arrow-left"></i> Back to Login</a>
        </div>
    </div>
</div>

<!-- Toast Container -->
<div id="toast-container" class="toast-container"></div>

<script src="<?= SITE_URL ?>/assets/js/main.js"></script>
</body>
</html>
