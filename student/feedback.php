<?php
/**
 * Student — Feedback & Suggestions
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';

requireStudent();
$pdo = db();
$sId = $_SESSION['student_id'];

$errors = [];
$success = '';

// Check if feedback already submitted
$fbStmt = $pdo->prepare("SELECT id FROM feedback WHERE student_id = ?");
$fbStmt->execute([$sId]);
$alreadySubmitted = $fbStmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $internship_rating = (int)($_POST['internship_rating'] ?? 5);
        $mentor_rating     = (int)($_POST['mentor_rating']     ?? 5);
        $internship_fb     = clean($_POST['internship_fb']     ?? '');
        $mentor_fb         = clean($_POST['mentor_fb']         ?? '');
        $suggestions       = clean($_POST['suggestions']       ?? '');
        $would_recommend   = isset($_POST['would_recommend']) ? 1 : 0;

        if ($alreadySubmitted) {
            $errors[] = 'You have already submitted your program feedback.';
        }

        if (empty($errors)) {
            $stmt = $pdo->prepare("
                INSERT INTO feedback (student_id, internship_rating, mentor_rating, internship_fb, mentor_fb, suggestions, would_recommend)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$sId, $internship_rating, $mentor_rating, $internship_fb, $mentor_fb, $suggestions, $would_recommend]);
            logActivity('student', $sId, 'submit_feedback', "Submitted program feedback");
            setFlash('success', 'Feedback submitted successfully! Thank you for your support.');
            redirect(SITE_URL . '/student/feedback.php');
        }
    }
}

$pageTitle  = 'Submit Feedback';
$userRole   = 'student';
$breadcrumb = [['label'=>'Feedback']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-student.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">Internship Feedback</h1>
            <p class="page-subtitle">Share your experience, mentor rating, and suggestions to help us improve</p>
        </div>
    </div>

    <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <div class="card" style="max-width: 720px; margin: 0 auto">
        <div class="card-header"><span class="card-title"><i class="fa fa-comment-dots"></i> Program Feedback Questionnaire</span></div>
        <div class="card-body">
            <?php if ($alreadySubmitted): ?>
            <div class="empty-state" style="padding: 40px 10px">
                <div class="empty-icon">🎉</div>
                <div class="empty-title">Feedback Received!</div>
                <div class="empty-msg">You have already submitted your feedback. We appreciate your valuable insights and rating.</div>
            </div>
            <?php else: ?>
            <form method="POST">
                <?= csrfField() ?>

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label">Internship Program Rating (1-5)</label>
                        <select name="internship_rating" class="form-control">
                            <option value="5">★★★★★ (5 - Excellent)</option>
                            <option value="4">★★★★☆ (4 - Good)</option>
                            <option value="3">★★★☆☆ (3 - Average)</option>
                            <option value="2">★★☆☆☆ (2 - Below Average)</option>
                            <option value="1">★☆☆☆☆ (1 - Poor)</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mentor Rating (1-5)</label>
                        <select name="mentor_rating" class="form-control">
                            <option value="5">★★★★★ (5 - Excellent)</option>
                            <option value="4">★★★★☆ (4 - Good)</option>
                            <option value="3">★★★☆☆ (3 - Average)</option>
                            <option value="2">★★☆☆☆ (2 - Below Average)</option>
                            <option value="1">★☆☆☆☆ (1 - Poor)</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Internship Program Experience / Review</label>
                    <textarea name="internship_fb" class="form-control" rows="3" placeholder="What parts of the program did you like or dislike?"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">Mentor Review &amp; Collaboration</label>
                    <textarea name="mentor_fb" class="form-control" rows="3" placeholder="Feedback on guidance, support, and alignment from your mentor…"></textarea>
                </div>

                <div class="form-group">
                    <label class="form-label">General Suggestions / Improvement Ideas</label>
                    <textarea name="suggestions" class="form-control" rows="3" placeholder="How can we make this internship program better for future batches?"></textarea>
                </div>

                <div class="form-group">
                    <label class="checkbox-label mt-8">
                        <input type="checkbox" name="would_recommend" value="1" checked>
                        I would recommend this internship to other students.
                    </label>
                </div>

                <button type="submit" class="btn btn-primary w-100 mt-24">
                    <i class="fa fa-paper-plane"></i> Submit Feedback
                </button>
            </form>
            <?php endif; ?>
        </div>
    </div>

</main>
</div>
</div>
<?php require_once BASE_PATH . '/includes/footer.php'; ?>
