<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils/Response.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) json_err('INVALID_ID', 400);

/** ambil info desain */
$st = db()->prepare("SELECT kind, media_path, allow_download, status, visibility
                     FROM designs WHERE id = :id LIMIT 1");
$st->execute([':id'=>$id]);
$row = $st->fetch();

if (!$row) json_err('NOT_FOUND', 404);
if ($row['kind'] !== 'image') json_err('NOT_IMAGE', 400);
if ((int)$row['allow_download'] !== 1) json_err('DOWNLOAD_BLOCKED', 403);
/** hanya publish & public yang boleh didownload */
if ($row['status'] !== 'published' || $row['visibility'] !== 'public') {
  json_err('FORBIDDEN', 403);
}

$rel = $row['media_path'] ?? '';
$abs = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . basename($rel);
if (!is_file($abs)) json_err('FILE_MISSING', 404);

/** kirim file */
$mime = mime_content_type($abs) ?: 'application/octet-stream';
$fname = basename($abs);

header('Content-Type: '.$mime);
header('Content-Length: '.filesize($abs));
header('Content-Disposition: attachment; filename="'.$fname.'"');

readfile($abs);
exit;
