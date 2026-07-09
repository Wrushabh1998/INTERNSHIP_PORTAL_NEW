<?php
/**
 * Secure File Upload Helper
 */

function uploadFile(
    array $file,
    string $destination,
    array $allowedMimes = [],
    int $maxSize = 0,
    string $prefix = ''
): array {
    $result = ['success' => false, 'filename' => '', 'error' => ''];

    if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        $codes = [
            UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
            UPLOAD_ERR_FORM_SIZE  => 'File exceeds form upload limit.',
            UPLOAD_ERR_PARTIAL    => 'File upload was incomplete.',
            UPLOAD_ERR_NO_FILE    => 'No file was uploaded.',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder.',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file.',
            UPLOAD_ERR_EXTENSION  => 'Upload blocked by server extension.',
        ];
        $result['error'] = $codes[$file['error'] ?? -1] ?? 'Unknown upload error.';
        return $result;
    }

    // Size check
    $maxBytes = $maxSize ?: MAX_FILE_SIZE;
    if ($file['size'] > $maxBytes) {
        $result['error'] = 'File too large. Max: ' . formatFileSize($maxBytes);
        return $result;
    }

    // MIME validation
    if ($allowedMimes) {
        $finfo    = new finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->file($file['tmp_name']);
        if (!in_array($mimeType, $allowedMimes)) {
            $result['error'] = 'File type not allowed. Allowed: ' . implode(', ', $allowedMimes);
            return $result;
        }
    }

    // Sanitize extension
    $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $safeExt  = preg_replace('/[^a-z0-9]/', '', $ext);
    $filename = ($prefix ?: 'file') . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $safeExt;

    // Ensure destination exists
    if (!is_dir($destination)) {
        mkdir($destination, 0755, true);
    }

    $fullPath = rtrim($destination, '/') . '/' . $filename;

    if (move_uploaded_file($file['tmp_name'], $fullPath)) {
        $result['success']  = true;
        $result['filename'] = $filename;
        $result['path']     = $fullPath;
        $result['size']     = $file['size'];
        $result['mime']     = $allowedMimes ? (new finfo(FILEINFO_MIME_TYPE))->file($fullPath) : '';
    } else {
        $result['error'] = 'Failed to save file. Check directory permissions.';
    }

    return $result;
}

function deleteFile(string $relativePath, string $baseDir): bool {
    $fullPath = rtrim($baseDir, '/') . '/' . ltrim($relativePath, '/');
    if (file_exists($fullPath) && is_file($fullPath)) {
        return unlink($fullPath);
    }
    return false;
}
