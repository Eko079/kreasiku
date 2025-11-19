<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Auth.php';
require_once __DIR__ . '/../models/Design.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('METHOD_NOT_ALLOWED', 405);

$uid = require_auth();
$ct = $_SERVER['CONTENT_TYPE'] ?? '';
if (str_contains($ct, 'application/json')) {
  $data = require_json();
  $id = (int)($data['id'] ?? 0);
} else {
  $id = (int)($_POST['id'] ?? $_GET['id'] ?? 0);
}
if ($id<=0) json_err('MISSING_ID', 422);

$db = db();
$st = $db->prepare("SELECT kind, media_path, owner_id FROM designs WHERE id=:id");
$st->execute([':id'=>$id]);
$row = $st->fetch();
if (!$row) json_err('NOT_FOUND',404);
if ((int)$row['owner_id'] !== $uid) json_err('FORBIDDEN',403);

// Bersihkan relasi images jika ada
// Bersihkan relasi images jika ada
$db->prepare('DELETE FROM design_images WHERE design_id=:id')->execute([':id'=>$id]);
$db->prepare("DELETE FROM designs WHERE id=:id")->execute([':id'=>$id]);

/* hapus file fisik bila image */
if ($row['kind']==='image' && !empty($row['media_path'])) {
  $abs = dirname(UPLOAD_DIR) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $row['media_path']);
  if (is_file($abs)) { @unlink($abs); }
}

json_ok(['deleted'=>true]);
