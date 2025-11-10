<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_err('METHOD_NOT_ALLOWED', 405);
}

$uid = require_auth();

/* ==== Ambil input ==== */
$category       = strtolower(trim($_POST['category'] ?? ''));
$title_in       = trim((string)($_POST['title'] ?? ''));
$description    = trim((string)($_POST['description'] ?? ''));
$visibility_in  = strtolower(trim((string)($_POST['visibility'] ?? 'public')));
$allow_comments = isset($_POST['allow_comments']) ? (int)!!$_POST['allow_comments'] : 1;
$allow_download = isset($_POST['allow_download']) ? (int)!!$_POST['allow_download'] : 0;
$figma_raw      = trim((string)($_POST['figma_url'] ?? ''));
$publish_at_raw = trim((string)($_POST['publish_at'] ?? '')); // optional: "YYYY-MM-DDTHH:MM"

/* Validasi kategori */
$allowed_cat = ['portofolio','website','cv','desain','logo'];
if (!in_array($category, $allowed_cat, true)) {
  json_err('INVALID_CATEGORY', 422);
}
$visibility = ($visibility_in === 'private') ? 'private' : 'public';

/* ==== Tentukan jenis konten (image / figma) ==== */
$kind       = '';
$media_path = null;
$figma_url  = null;

if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
  // ---- Upload gambar ----
  $kind = 'image';
  $f = $_FILES['image'];

  if ($f['error'] !== UPLOAD_ERR_OK) json_err('UPLOAD_FAILED', 422);

  $fi = finfo_open(FILEINFO_MIME_TYPE);
  $mime = finfo_file($fi, $f['tmp_name']); finfo_close($fi);
  $allow = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp','image/gif'=>'gif'];
  if (!isset($allow[$mime])) json_err('UNSUPPORTED_IMAGE', 422);

  if (!is_dir(UPLOAD_DIR)) @mkdir(UPLOAD_DIR, 0775, true);

  $name = 'img_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$allow[$mime];
  $dest = rtrim(UPLOAD_DIR,'/\\') . DIRECTORY_SEPARATOR . $name;

  if (!move_uploaded_file($f['tmp_name'], $dest)) {
    json_err('MOVE_FAILED', 500);
  }

  // disimpan relatif terhadap /api/storage
  $media_path = 'uploads/' . $name;

} elseif ($figma_raw !== '') {
  // ---- Link Figma ----
  $kind = 'figma';
  $u = parse_url($figma_raw);
  if (empty($u['host']) || stripos($u['host'], 'figma.com') === false) {
    json_err('INVALID_FIGMA_URL', 422);
  }
  $figma_url = $figma_raw;

} else {
  // Tidak ada image & tidak ada figma_url
  json_err('MISSING_FIELDS', 422);
}

/* ==== Tentukan status publish & waktu ==== */
$status        = 'published';
$scheduled_at  = null;
$published_at  = date('Y-m-d H:i:s');

if ($publish_at_raw !== '') {
  // normalisasi format "YYYY-MM-DDTHH:MM" menjadi "Y-m-d H:i:s"
  $ts = strtotime($publish_at_raw);
  if ($ts === false) {
    json_err('INVALID_PUBLISH_AT', 422);
  }
  $now = time();
  if ($ts > $now) {
    $status       = 'scheduled';
    $scheduled_at = date('Y-m-d H:i:s', $ts);
    $published_at = null; // akan diisi saat job publish jalan
  }
}

/* Pastikan title tidak null untuk skema NOT NULL */
$title = ($title_in !== '') ? $title_in : 'Untitled';

/* ==== Simpan ke DB ==== */
try {
  $db = db();
  $sql = "INSERT INTO designs
            (owner_id, category, kind, media_path, figma_url,
             title, description, allow_comments, allow_download, visibility,
             status, scheduled_at, published_at, created_at)
          VALUES
            (:owner_id, :category, :kind, :media_path, :figma_url,
             :title, :description, :allow_comments, :allow_download, :visibility,
             :status, :scheduled_at, :published_at, NOW())";

  $st = $db->prepare($sql);
  $st->execute([
    ':owner_id'       => $uid,
    ':category'       => $category,
    ':kind'           => $kind,
    ':media_path'     => $media_path,
    ':figma_url'      => $figma_url,
    ':title'          => $title,
    ':description'    => ($description !== '' ? $description : null),
    ':allow_comments' => $allow_comments,
    ':allow_download' => $allow_download,
    ':visibility'     => $visibility,
    ':status'         => $status,
    ':scheduled_at'   => $scheduled_at,
    ':published_at'   => $published_at,
  ]);

  $id = (int)$db->lastInsertId();

  json_ok([
    'id'             => $id,
    'kind'           => $kind,
    'category'       => $category,
    'title'          => $title,
    'media_path'     => $media_path,
    'figma_url'      => $figma_url,
    'allow_comments' => $allow_comments,
    'allow_download' => $allow_download,
    'visibility'     => $visibility,
    'status'         => $status,
    'scheduled_at'   => $scheduled_at,
    'published_at'   => $published_at
  ]);

} catch (Throwable $e) {
  json_err('DB_ERROR: '.$e->getMessage(), 500);
}
