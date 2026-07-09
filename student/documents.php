<?php
/**
 * Student — Document Center
 */
define('BASE_PATH', dirname(__DIR__));
require_once BASE_PATH . '/config/config.php';
require_once BASE_PATH . '/config/database.php';
require_once BASE_PATH . '/includes/functions.php';
require_once BASE_PATH . '/includes/auth.php';
require_once BASE_PATH . '/helpers/upload.php';

requireStudent();
$pdo = db();
$sId = $_SESSION['student_id'];

$errors = [];

// Handle document upload
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!validateCsrfToken($_POST[CSRF_TOKEN_NAME] ?? '')) {
        $errors[] = 'Invalid CSRF token.';
    } else {
        $title   = clean($_POST['title'] ?? '');
        $docType = in_array($_POST['doc_type']??'',['Resume','Offer Letter','Joining Letter','ID Card','Certificate','Project Report','PPT','Other']) ? $_POST['doc_type'] : 'Other';

        if (!$title) {
            $errors[] = 'Document title is required.';
        }

        if (isset($_FILES['doc_file']) && $_FILES['doc_file']['error'] === UPLOAD_ERR_OK) {
            $dest = UPLOAD_DOCUMENTS;
            $up = uploadFile($_FILES['doc_file'], $dest, ALLOWED_DOC_TYPES, MAX_FILE_SIZE, 'doc');
            if ($up['success']) {
                $stmt = $pdo->prepare("
                    INSERT INTO documents (student_id, doc_type, title, file_path, file_size, mime_type)
                    VALUES (?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([$sId, $docType, $title, $up['filename'], $up['size'], $up['mime']]);

                // Sync profile links if relevant
                if ($docType === 'Resume') {
                    $pdo->prepare("UPDATE students SET resume_path=? WHERE id=?")->execute([$up['filename'], $sId]);
                } elseif ($docType === 'ID Card') {
                    $pdo->prepare("UPDATE students SET aadhaar_path=? WHERE id=?")->execute([$up['filename'], $sId]);
                }

                logActivity('student', $sId, 'upload_document', "Uploaded document: $title ($docType)");
                setFlash('success', 'Document uploaded successfully.');
                redirect(SITE_URL . '/student/documents.php');
            } else {
                $errors[] = 'File upload failed: ' . $up['error'];
            }
        } else {
            $errors[] = 'Please select a file to upload.';
        }
    }
}

// Handle document delete
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    $stmt = $pdo->prepare("SELECT file_path FROM documents WHERE id = ? AND student_id = ?");
    $stmt->execute([$id, $sId]);
    $file = $stmt->fetchColumn();

    if ($file) {
        deleteFile($file, UPLOAD_DOCUMENTS);
        $pdo->prepare("DELETE FROM documents WHERE id = ?")->execute([$id]);
        logActivity('student', $sId, 'delete_document', "Deleted document ID: $id");
        setFlash('success', 'Document deleted successfully.');
    }
    redirect(SITE_URL . '/student/documents.php');
}

// Fetch all documents for student
$docs = $pdo->prepare("SELECT * FROM documents WHERE student_id = ? ORDER BY created_at DESC");
$docs->execute([$sId]);
$docs = $docs->fetchAll();

$pageTitle  = 'Documents';
$userRole   = 'student';
$breadcrumb = [['label'=>'Documents']];
require_once BASE_PATH . '/includes/header.php';
?>

<div class="app-layout">
<?php require_once BASE_PATH . '/includes/sidebar-student.php'; ?>
<div class="main-content" id="main-content">
<?php require_once BASE_PATH . '/includes/topnav.php'; ?>
<main class="page-content">

    <div class="page-header">
        <div>
            <h1 class="page-title">Documents Vault 📁</h1>
            <p class="page-subtitle">Manage internship letters, certificates, and report drafts</p>
        </div>
        <button class="btn btn-primary" data-modal-open="upload-modal">
            <i class="fa fa-upload"></i> Upload Document
        </button>
    </div>

    <?php foreach ($errors as $e): ?>
    <div class="alert alert-danger"><i class="fa fa-exclamation-circle"></i> <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <!-- Document List -->
    <div class="card table-card">
        <div class="card-header"><span class="card-title">My Document Repos</span></div>
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Title / File Name</th>
                        <th>Document Type</th>
                        <th>File Size</th>
                        <th>Uploaded Date</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($docs)): ?>
                    <tr><td colspan="5"><div class="empty-state"><div class="empty-icon">📁</div><div class="empty-title">No documents uploaded</div><div class="empty-msg">Use the button above to upload templates or reports.</div></div></td></tr>
                    <?php else: foreach ($docs as $d): ?>
                    <tr>
                        <td>
                            <div class="fw-600 text-sm"><?= htmlspecialchars($d['title']) ?></div>
                            <div class="text-xs text-muted"><?= htmlspecialchars($d['file_path']) ?></div>
                        </td>
                        <td><span class="badge badge-info"><?= $d['doc_type'] ?></span></td>
                        <td class="text-sm"><?= formatFileSize($d['file_size']) ?></td>
                        <td class="text-xs text-muted"><?= formatDate($d['created_at']) ?></td>
                        <td>
                            <div class="td-actions">
                                <a href="<?= SITE_URL ?>/uploads/documents/<?= htmlspecialchars($d['file_path']) ?>" target="_blank" class="btn btn-ghost btn-icon-sm" title="Download">
                                    <i class="fa fa-download"></i>
                                </a>
                                <a href="?action=delete&id=<?= $d['id'] ?>" class="btn btn-danger btn-icon-sm" title="Delete" data-confirm-delete="Delete this document?">
                                    <i class="fa fa-trash"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </div>

</main>
</div>
</div>

<!-- Upload Modal -->
<div class="modal-overlay" id="upload-modal">
    <div class="modal" style="max-width:480px">
        <div class="modal-header">
            <span class="modal-title">Upload New Document</span>
            <button class="modal-close">✕</button>
        </div>
        <form method="POST" enctype="multipart/form-data">
            <?= csrfField() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Document Title <span class="required">*</span></label>
                    <input type="text" name="title" class="form-control" required placeholder="e.g. Joining Report Draft">
                </div>
                <div class="form-group">
                    <label class="form-label">Document Type <span class="required">*</span></label>
                    <select name="doc_type" class="form-control" required>
                        <option>Resume</option>
                        <option>Offer Letter</option>
                        <option>Joining Letter</option>
                        <option>ID Card</option>
                        <option>Certificate</option>
                        <option>Project Report</option>
                        <option>PPT</option>
                        <option>Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label class="form-label">Select File <span class="required">*</span></label>
                    <input type="file" name="doc_file" class="form-control" required>
                    <div class="form-text">PDF, DOC, DOCX, PPT, PPTX or Images allowed. Max 5MB.</div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-modal-close>Cancel</button>
                <button type="submit" class="btn btn-primary"><i class="fa fa-upload"></i> Upload</button>
            </div>
        </form>
    </div>
</div>

<?php require_once BASE_PATH . '/includes/footer.php'; ?>
