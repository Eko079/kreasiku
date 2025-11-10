<?php
declare(strict_types=1);

require_once __DIR__.'/../utils/Response.php';
require_once __DIR__.'/../middleware/Auth.php';
require_once __DIR__.'/../models/Design.php';
require_once __DIR__.'/../services/UploadService.php';
require_once __DIR__.'/../config.php';

class DesignsController {
  public static function index(): void {
    $q = [
      'category'   => isset($_GET['category']) ? strtolower(trim($_GET['category'])) : null,
      'owner_id'   => isset($_GET['ownerId']) ? (int)$_GET['ownerId'] : null,
      'sort'       => $_GET['sort'] ?? null,
      'visibility' => 'public',
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
    $commentEnabled  = isset($_POST['comment_enabled']) ? (int)$_POST['comment_enabled'] === 1 : true;
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
        'owner_id'=>$uid,'type'=>'image','title'=>$title ?: 'Gambar','description'=>$desc,
        'category'=>$category,'visibility'=>$visibility,'comment_enabled'=>$commentEnabled,
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
      $commentEnabled  = isset($in['comment_enabled']) ? (bool)$in['comment_enabled'] : true;
      $status          = in_array($in['status'] ?? 'published', ['draft','scheduled','published'], true) ? $in['status'] : 'published';
      $scheduledAt     = $in['scheduled_at'] ?? null;

      if (!$cat)   json_err('CATEGORY_REQUIRED', 422);
      if (!$figma) json_err('FIGMA_URL_REQUIRED', 422);
      if (!str_contains($figma, 'figma.com')) json_err('FIGMA_URL_INVALID', 422);

      $id = Design::create([
        'owner_id'=>$uid,'type'=>'figma','title'=>$title ?: 'Figma','description'=>$desc,
        'category'=>$cat,'visibility'=>$visibility,'comment_enabled'=>$commentEnabled,
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
      'comment_enabled' => 'comment_enabled',
      'allow_download'  => 'allow_download',
      'status'          => 'status',
      'scheduled_at'    => 'scheduled_at',
    ];
    foreach ($map as $k=>$col) {
      if (array_key_exists($k,$in)) {
        $fields[] = "$col = :$col";
        $params[":$col"] = in_array($k,['comment_enabled','allow_download'])
          ? ((int)!!$in[$k])
          : $in[$k];
      }
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
    $pdo->prepare('DELETE FROM designs WHERE id=?')->execute([$id]);
    json_ok(['message'=>'DELETED']);
  }

  /** GET /designs/:id/download  (stream file pertama jika allow_download) */
  public static function download(int $id): void {
    $row = Design::findById($id, true, (int)($_SESSION['uid'] ?? 0));
    if (!$row) json_err('NOT_FOUND',404);
    if ($row['type']!=='image') json_err('NOT_IMAGE',400);
    if (!(int)$row['allow_download']) json_err('DOWNLOAD_DISABLED',403);

    $imgs = Design::getImages($id);
    if (!$imgs) json_err('NO_FILE',404);
    $first = $imgs[0];

    // ambil filename lalu map ke storage path
    $name = basename(parse_url($first, PHP_URL_PATH));
    $path = __DIR__.'/../storage/uploads/images/'.$name;
    if (!is_file($path)) json_err('FILE_MISSING',404);

    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="'.$name.'"');
    header('Content-Length: '.filesize($path));
    readfile($path);
    exit;
  }

  private static function toClient(array $r, array $imgs): array {
    return [
      'id'            => (int)$r['id'],
      'ownerId'       => (int)$r['owner_id'],
      'type'          => $r['type'],
      'title'         => $r['title'],
      'desc'          => $r['description'],
      'category'      => $r['category'],
      'visibility'    => $r['visibility'],
      'commentEnabled'=> (bool)$r['comment_enabled'],
      'allowDownload' => (bool)$r['allow_download'],
      'status'        => $r['status'],
      'scheduledAt'   => $r['scheduled_at'],
      'publishedAt'   => $r['published_at'],
      'likesCount'    => (int)$r['likes_count'],
      'savesCount'    => (int)$r['saves_count'],
      'commentsCount' => (int)$r['comments_count'],
      'figmaUrl'      => $r['figma_url'],
      'images'        => $imgs,
      'createdAt'     => $r['created_at'],
      'updatedAt'     => $r['updated_at'],
    ];
  }
}
