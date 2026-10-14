<?php
/**
 * GET  ?job_id=ID -> photos for an assigned job.
 * POST multipart/form-data { job_id, description?, type?: Before|After|Progress|Issue, photo: file }
 *      -> upload a JPEG/PNG/WebP (max 5 MB) into JobCardPhotos.
 * POST { action: 'delete', photo_id } -> delete a photo from an assigned, open job.
 */
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/mechanic-data.php';

$user = require_api_role('Mechanic');
$mechanicId = $user['id'];
$method = require_method(['GET', 'POST']);

const PHOTO_MAX_BYTES = 5 * 1024 * 1024;
const PHOTO_TYPES = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
const PHOTO_DIR = __DIR__ . '/../../uploads/repair-photos';
const PHOTO_URL_PREFIX = '../../uploads/repair-photos/';
const PHOTO_KINDS = ['Before', 'After', 'Progress', 'Issue'];

try {
    if ($method === 'GET') {
        $jobId = input_id($_GET['job_id'] ?? null);
        if (!$jobId || !mechanic_job($pdo, $mechanicId, $jobId)) {
            json_error('Job not found.', 404);
        }
        json_ok(job_photos($pdo, $jobId));
    }

    $isUpload = stripos($_SERVER['CONTENT_TYPE'] ?? '', 'multipart/form-data') === 0;

    if (!$isUpload) {
        $input = read_json_body();
        if (($input['action'] ?? '') !== 'delete') {
            json_error('Unknown action.', 422);
        }
        $photoId = input_id($input['photo_id'] ?? null);
        $stmt = $pdo->prepare("
            SELECT p.photo_url, j.status FROM JobCardPhotos p JOIN JobCards j ON p.job_card_id = j.id
             WHERE p.id = ? AND j.mechanic_id = ?");
        $stmt->execute([$photoId ?: 0, $mechanicId]);
        $photo = $stmt->fetch();
        if (!$photo) {
            json_error('Photo not found.', 404);
        }
        if (in_array($photo['status'], ['Completed', 'Delivered'], true)) {
            json_error('Photos on a finished job cannot be deleted.', 409);
        }
        $pdo->prepare("DELETE FROM JobCardPhotos WHERE id = ?")->execute([$photoId]);

        // Remove the file only if it is one of our uploads (seed rows point at shared assets)
        if (strpos($photo['photo_url'], PHOTO_URL_PREFIX) === 0) {
            $file = PHOTO_DIR . '/' . basename($photo['photo_url']);
            if (is_file($file)) {
                @unlink($file);
            }
        }
        json_ok(['id' => $photoId, 'deleted' => true]);
    }

    // Upload
    $jobId = input_id($_POST['job_id'] ?? null);
    $job = $jobId ? mechanic_job($pdo, $mechanicId, $jobId) : null;
    if (!$job) {
        json_error('Job not found.', 404);
    }
    if (!$job['is_open']) {
        json_error('Photos can only be added to open jobs.', 409);
    }
    $description = input_text($_POST['description'] ?? '', 0, 255);
    if ($description === null) {
        json_error('Caption must be 255 characters or fewer.', 422);
    }
    $type = $_POST['type'] ?? 'Progress';
    if (!in_array($type, PHOTO_KINDS, true)) {
        json_error('Photo type must be one of: ' . implode(', ', PHOTO_KINDS) . '.', 422);
    }

    $file = $_FILES['photo'] ?? null;
    $code = is_array($file) && is_int($file['error'] ?? null) ? $file['error'] : UPLOAD_ERR_NO_FILE;
    if ($code !== UPLOAD_ERR_OK) {
        json_error($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE ? 'Photo is larger than 5 MB.' : 'Choose a photo to upload.', 422);
    }
    if (!is_uploaded_file($file['tmp_name']) || $file['size'] > PHOTO_MAX_BYTES || $file['size'] === 0) {
        json_error('Photo must be a JPEG, PNG or WebP image up to 5 MB.', 422);
    }
    $mime = (new finfo(FILEINFO_MIME_TYPE))->file($file['tmp_name']);
    if (!isset(PHOTO_TYPES[$mime]) || @getimagesize($file['tmp_name']) === false) {
        json_error('Photo must be a JPEG, PNG or WebP image up to 5 MB.', 422);
    }

    if (!is_dir(PHOTO_DIR) && !mkdir(PHOTO_DIR, 0755, true)) {
        throw new RuntimeException('Cannot create upload directory');
    }
    // Server-chosen name and extension; nothing from the client's filename is kept
    $name = 'job' . $jobId . '-' . bin2hex(random_bytes(12)) . '.' . PHOTO_TYPES[$mime];
    if (!move_uploaded_file($file['tmp_name'], PHOTO_DIR . '/' . $name)) {
        throw new RuntimeException('move_uploaded_file failed');
    }

    $pdo->prepare("INSERT INTO JobCardPhotos (job_card_id, photo_url, type, description) VALUES (?, ?, ?, ?)")
        ->execute([$jobId, PHOTO_URL_PREFIX . $name, $type, $description !== '' ? $description : null]);

    json_ok([
        'id' => (int) $pdo->lastInsertId(),
        'photo_url' => PHOTO_URL_PREFIX . $name,
        'type' => $type,
        'description' => $description,
    ], 201);
} catch (Throwable $e) {
    json_server_error($e);
}
