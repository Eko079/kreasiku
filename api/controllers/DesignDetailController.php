<?php
declare(strict_types=1);

require_once __DIR__.'/../utils/Response.php';
require_once __DIR__.'/../utils/Auth.php';
require_once __DIR__.'/../config.php';

class DesignDetailController {
  public static function show(int $id): void {
    global $pdo;
    $uid  = optional_auth();

    // Ambil desain + izin
    $sql = "SELECT d.*, u.name AS owner_name, u.avatar AS owner_avatar
            FROM designs d JOIN users u ON u.id=d.owner_id
            WHERE d.id=? LIMIT 1";
    $st = $pdo->prepare($sql); $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) json_err('NOT_FOUND',404);

    $isOwner = $uid && (int)$row['owner_id']===(int)$uid;
    $isVisible = ($row['status']==='published' && $row['visibility']==='public') || $isOwner;
    if (!$isVisible) json_err('FORBIDDEN',403);

    // liked/saved oleh viewer
    $liked = false; $saved=false;
    if ($uid) {
      $q1 = $pdo->prepare('SELECT 1 FROM design_likes WHERE design_id=? AND user_id=? LIMIT 1');
      $q1->execute([$id,$uid]); $liked = (bool)$q1->fetchColumn();
      $q2 = $pdo->prepare('SELECT 1 FROM design_saves WHERE design_id=? AND user_id=? LIMIT 1');
      $q2->execute([$id,$uid]); $saved = (bool)$q2->fetchColumn();
    }

    // hitung agregat
    $likes = (int)$pdo->query("SELECT COUNT(*) FROM design_likes WHERE design_id=".$id)->fetchColumn();
    $saves = (int)$pdo->query("SELECT COUNT(*) FROM design_saves WHERE design_id=".$id)->fetchColumn();
    $comms = (int)$pdo->query("SELECT COUNT(*) FROM comments WHERE design_id=".$id)->fetchColumn();

    json_ok([
      'data'=>[
        'id'            => (int)$row['id'],
        'kind'          => $row['kind'],                 // image|figma
        'title'         => $row['title'],
        'desc'          => $row['description'],
        'category'      => $row['category'],
        'visibility'    => $row['visibility'],
        'commentEnabled'=> (bool)$row['allow_comments'],
        'allowDownload' => (bool)$row['allow_download'],
        'status'        => $row['status'],
        'scheduledAt'   => $row['scheduled_at'],
        'publishedAt'   => $row['published_at'],
        'likesCount'    => $likes,
        'savesCount'    => $saves,
        'commentsCount' => $comms,
        'figmaUrl'      => $row['figma_url'],
        // untuk image tunggal, kirimkan absolute URL dari media_path
        'media'         => $row['media_path'] ? (rtrim(base_url(),'/').'/storage/'.ltrim($row['media_path'],'/')) : null,
        'ownerId'       => (int)$row['owner_id'],
        'ownerName'     => $row['owner_name'],
        'ownerAvatar'   => $row['owner_avatar'],
        'liked'         => $liked,
        'saved'         => $saved,
        'createdAt'     => $row['created_at'],
        'updatedAt'     => $row['updated_at'] ?? null,
      ]
    ]);
  }

  // Download versi “media_path” sederhana (bukan design_images)
  public static function download(int $id): void {
    global $pdo;
    $uid  = optional_auth();

    $st = $pdo->prepare('SELECT owner_id, allow_download, status, visibility, kind, media_path FROM designs WHERE id=?');
    $st->execute([$id]); $row = $st->fetch();
    if (!$row) json_err('NOT_FOUND',404);

    $isOwner = $uid && (int)$row['owner_id']===(int)$uid;
    if ($row['kind']!=='image') json_err('NOT_IMAGE',400);
    if (!(int)$row['allow_download']) json_err('DOWNLOAD_DISABLED',403);
    if (!$isOwner && !($row['status']==='published' && $row['visibility']==='public')) {
      json_err('FORBIDDEN',403);
    }

    $rel = $row['media_path'] ?: '';
    $abs = dirname(UPLOAD_DIR) . DIRECTORY_SEPARATOR . str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $rel);
    if (!is_file($abs)) json_err('FILE_MISSING',404);

    $mime = mime_content_type($abs) ?: 'application/octet-stream';
    header('Content-Type: '.$mime);
    header('Content-Length: '.filesize($abs));
    header('Content-Disposition: attachment; filename="'.basename($abs).'"');
    readfile($abs);
    exit;
  }
}
