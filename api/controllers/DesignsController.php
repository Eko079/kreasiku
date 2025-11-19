<?php
declare(strict_types=1);

require_once __DIR__.'/../utils/Response.php';
require_once __DIR__.'/../middleware/Auth.php';
require_once __DIR__.'/../models/Design.php';
require_once __DIR__.'/../services/UploadService.php';
require_once __DIR__.'/../config.php';
require_once __DIR__.'/../services/StorageBin.php';

class DesignsController {
  public static function index(): void {
    $q = [
      'category'   => isset($_GET['category']) ? strtolower(trim($_GET['category'])) : null,
      'owner_id'   => isset($_GET['ownerId']) ? (int)$_GET['ownerId'] : null,
      'sort'       => $_GET['sort'] ?? null,
      'visibility' => 'public',
      'status'     => 'published',
    ];
    $rows = Design::list($q);
    $out = [];
    foreach ($rows as $r) {
      $imgs = Design::getImages((int)$r['id']);
      $out[] = self::toClient($r, $imgs);
    }
    json_ok(['data'=>$out]);
  }

  public static function show(int $id): void {
    $viewer = $_SESSION['uid'] ?? 0;
    $row = Design::findById($id, true, (int)$viewer);
    if (!$row) json_err('NOT_FOUND', 404);
    if ($row['visibility'] !== 'public' && (int)$row['owner_id'] !== (int)$viewer) {
      json_err('FORBIDDEN', 403);
    }
    $imgs = Design::getImages($id);
    json_ok(['data'=>self::toClient($row, $imgs)]);
  }

  public static function create(): void {
    $uid = require_auth();
    $isMultipart = (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'multipart/form-data'));

    $visibility      = ($_POST['visibility'] ?? 'public') === 'private' ? 'private' : 'public';
    $commentField    = array_key_exists('comment_enabled', $_POST)
      ? $_POST['comment_enabled']
      : ($_POST['allow_comments'] ?? null);
    $commentAllowed  = $commentField === null
      ? true
      : (is_bool($commentField) ? $commentField : ((int)$commentField === 1));
    $allowDownload   = isset($_POST['allow_download']) ? (int)$_POST['allow_download'] === 1 : false;
    $status          = in_array($_POST['status'] ?? 'published', ['draft','scheduled','published'], true) ? $_POST['status'] : 'published';
    $scheduledAt     = $_POST['scheduled_at'] ?? null;

    if ($isMultipart) {
      $title    = trim($_POST['title'] ?? '');
      $desc     = trim($_POST['description'] ?? '');
      $category = strtolower(trim($_POST['category'] ?? ''));
      if (!$category) json_err('CATEGORY_REQUIRED', 422);

      $urls = isset($_FILES['images']) ? UploadService::saveImages($_FILES['images']) : [];
      if (empty($urls)) json_err('IMAGES_REQUIRED', 422);

      $id = Design::create([
        'owner_id'=>$uid,'kind'=>'image','title'=>$title ?: 'Gambar','description'=>$desc,
        'category'=>$category,'visibility'=>$visibility,'allow_comments'=>$commentAllowed,
        'allow_download'=>$allowDownload,'status'=>$status,'scheduled_at'=>$scheduledAt,'figma_url'=>null,
      ]);
      foreach ($urls as $i=>$u) Design::addImage($id,$u,$i);

      $row  = Design::findById($id,true,$uid);
      $imgs = Design::getImages($id);
      json_ok(['data'=>self::toClient($row,$imgs)],201);

    } else {
      $in   = require_json();
      $title = trim($in['title'] ?? '');
      $desc  = trim($in['description'] ?? '');
      $cat   = strtolower(trim($in['category'] ?? ''));
      $figma = trim($in['figma_url'] ?? '');

      $visibility      = ($in['visibility'] ?? 'public') === 'private' ? 'private' : 'public';
      $commentInput    = array_key_exists('comment_enabled',$in) ? $in['comment_enabled'] : ($in['allow_comments'] ?? null);
      if (is_string($commentInput) && $commentInput === '') $commentInput = null;
      $commentAllowed  = $commentInput === null ? true : (is_bool($commentInput) ? $commentInput : ((int)$commentInput === 1));
      $status          = in_array($in['status'] ?? 'published', ['draft','scheduled','published'], true) ? $in['status'] : 'published';
      $scheduledAt     = $in['scheduled_at'] ?? null;

      if (!$cat)   json_err('CATEGORY_REQUIRED', 422);
      if (!$figma) json_err('FIGMA_URL_REQUIRED', 422);
      if (!str_contains($figma, 'figma.com')) json_err('FIGMA_URL_INVALID', 422);

      $id = Design::create([
        'owner_id'=>$uid,'kind'=>'figma','title'=>$title ?: 'Figma','description'=>$desc,
        'category'=>$cat,'visibility'=>$visibility,'allow_comments'=>$commentAllowed,
        'allow_download'=>false,'status'=>$status,'scheduled_at'=>$scheduledAt,'figma_url'=>$figma,
      ]);
      $row = Design::findById($id,true,$uid);
      json_ok(['data'=>self::toClient($row,[])],201);
    }
  }

  /** PATCH /designs/:id  (edit judul/desc/flags/visibility/schedule) */
  public static function update(int $id): void {
    $uid = require_auth();
    $row = Design::findById($id, true, $uid);
    if (!$row) json_err('NOT_FOUND',404);
    if ((int)$row['owner_id'] !== (int)$uid) json_err('FORBIDDEN',403);

    $in = require_json();
    $fields = [];
    $params = [];

    $map = [
      'title'           => 'title',
      'description'     => 'description',
      'visibility'      => 'visibility',
      'category'        => 'category',
      'allow_download'  => 'allow_download',
      'status'          => 'status',
      'scheduled_at'    => 'scheduled_at',
    ];
    foreach ($map as $k=>$col) {
      if (array_key_exists($k,$in)) {
        $fields[] = "$col = :$col";
        $params[":$col"] = in_array($k,['allow_download'])
          ? ((int)!!$in[$k])
          : $in[$k];
      }
    }
    if (array_key_exists('comment_enabled', $in) || array_key_exists('allow_comments',$in)) {
      $val = array_key_exists('allow_comments',$in) ? $in['allow_comments'] : $in['comment_enabled'];
      $fields[] = 'allow_comments = :allow_comments';
      $params[':allow_comments'] = (int)!!$val;
    }
    if (isset($in['status']) && $in['status']==='published') {
      $fields[] = 'published_at = NOW()';
    }
    if (!$fields) json_ok(['message'=>'NO_CHANGE']);
    $params[':id'] = $id;

    global $pdo;
    $sql = 'UPDATE designs SET '.implode(', ',$fields).', updated_at=NOW() WHERE id=:id';
    $pdo->prepare($sql)->execute($params);

    $new = Design::findById($id,true,$uid);
    $imgs = Design::getImages($id);
    json_ok(['data'=>self::toClient($new,$imgs)]);
  }

  /** DELETE /designs/:id */
  public static function destroy(int $id): void {
    $uid = require_auth();
    global $pdo;
    $row = Design::findById($id,true,$uid);
    if (!$row) json_err('NOT_FOUND',404);
    if ((int)$row['owner_id'] !== (int)$uid) json_err('FORBIDDEN',403);

    $imgStmt = $pdo->prepare('SELECT file_path FROM design_images WHERE design_id=?');
    $imgStmt->execute([$id]);
    while ($file = $imgStmt->fetchColumn()) {
      StorageBin::moveToBin($file);
    }
    $pdo->prepare('DELETE FROM design_images WHERE design_id=?')->execute([$id]);

    if (!empty($row['media_path'])) {
      StorageBin::moveToBin($row['media_path']);
    }

    $pdo->prepare('DELETE FROM designs WHERE id=?')->execute([$id]);
    json_ok(['message'=>'DELETED']);
  }

  /** GET /designs/:id/download  (stream file pertama jika allow_download) */
  public static function download(int $id): void {
    global $pdo;
    $row = Design::findById($id, true, (int)($_SESSION['uid'] ?? 0));
    if (!$row) json_err('NOT_FOUND',404);
    if (($row['kind'] ?? $row['type'] ?? null) !== 'image') json_err('NOT_IMAGE',400);
    if (!(int)$row['allow_download']) json_err('DOWNLOAD_DISABLED',403);

    $stmt = $pdo->prepare('SELECT file_path FROM design_images WHERE design_id=? ORDER BY position ASC, id ASC LIMIT 1');
    $stmt->execute([$id]);
    $filePath = $stmt->fetchColumn();
    if (!$filePath) {
      $filePath = $row['media_path'] ?? null;
    }
    if (!$filePath) json_err('NO_FILE',404);

    $clean = ltrim(str_replace('\\','/', (string)$filePath), '/');
    if ($clean === '') json_err('FILE_MISSING',404);
    if (str_starts_with($clean, 'storage/')) {
      $clean = substr($clean, strlen('storage/'));
    }
    $abs = rtrim(dirname(UPLOAD_DIR), '/\\') . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $clean);
    if (!is_file($abs)) json_err('FILE_MISSING',404);

    $mime = mime_content_type($abs) ?: 'application/octet-stream';
    header('Content-Type: '.$mime);
    header('Content-Disposition: attachment; filename="'.basename($abs).'"');
    header('Content-Length: '.filesize($abs));
    readfile($abs);
    exit;
  }

  private static function mediaUrl(?string $path): ?string {
    if (!$path) return null;
    $clean = ltrim(str_replace('\\','/', (string)$path), '/');
    if ($clean === '') return null;
    $apiBase  = rtrim(base_url(), '/');
    $rootBase = rtrim(dirname($apiBase), '/');
    if (str_starts_with($clean, 'storage/')) return $rootBase . '/' . $clean;
    return $rootBase . '/storage/' . $clean;
  }

  private static function toClient(array $r, array $imgs): array {
    $kind = $r['kind'] ?? $r['type'] ?? null;
    $legacyPath = self::mediaUrl($r['media_path'] ?? null);
    $images = $imgs;
    if (empty($images) && $legacyPath) {
      $images = [$legacyPath];
    }
    return [
      'id'            => (int)$r['id'],
      'ownerId'       => (int)$r['owner_id'],
      'type'          => $kind, // legacy alias
      'kind'          => $kind,
      'title'         => $r['title'],
      'desc'          => $r['description'],
      'category'      => $r['category'],
      'visibility'    => $r['visibility'],
      'commentEnabled'=> (bool)($r['comment_enabled'] ?? $r['allow_comments'] ?? 1),
      'allowDownload' => (bool)$r['allow_download'],
      'status'        => $r['status'],
      'scheduledAt'   => $r['scheduled_at'],
      'publishedAt'   => $r['published_at'],
      'likesCount'    => (int)$r['likes_count'],
      'savesCount'    => (int)$r['saves_count'],
      'commentsCount' => (int)$r['comments_count'],
      'figmaUrl'      => $r['figma_url'],
      'mediaPath'     => $legacyPath,
      'media_path'    => $legacyPath,
      'images'        => $images,
      'createdAt'     => $r['created_at'],
      'updatedAt'     => $r['updated_at'],
    ];
  }
}
