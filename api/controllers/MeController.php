<?php
declare(strict_types=1);

require_once __DIR__.'/../utils/Response.php';
require_once __DIR__.'/../utils/Pagination.php';
require_once __DIR__.'/../utils/Auth.php';
require_once __DIR__.'/../config.php';

function media_url(?string $rel): ?string {
  if (!$rel) return null;
  return rtrim(base_url(),'/').'/storage/'.ltrim($rel,'/');
}

class MeController {
  public static function myDesigns(): void {
    $uid = require_auth();
    global $pdo;
    [$page,$per,$offset] = page_params();

    $w = ['owner_id = ?'];
    $p = [$uid];

    if (!empty($_GET['status'])) {
      $status = strtolower($_GET['status']);
      if (in_array($status, ['draft','scheduled','published'], true)) {
        $w[] = 'status = ?'; $p[] = $status;
      }
    }
    if (!empty($_GET['category'])) {
      $w[] = 'category = ?'; $p[] = strtolower($_GET['category']);
    }

    $order = 'updated_at DESC';
    $sort = $_GET['sort'] ?? 'updated';
    if ($sort==='newest') $order = 'created_at DESC';
    if ($sort==='likes')  $order = 'likes_count DESC, updated_at DESC';
    if ($sort==='saves')  $order = 'saves_count DESC, updated_at DESC';

    $st = $pdo->prepare('SELECT COUNT(*) FROM designs WHERE '.implode(' AND ',$w));
    $st->execute($p);
    $total = (int)$st->fetchColumn();

    $sql = 'SELECT * FROM designs WHERE '.implode(' AND ',$w).' ORDER BY '.$order.' LIMIT ? OFFSET ?';
    $st  = $pdo->prepare($sql);
    $st->execute(array_merge($p, [$per, $offset]));
    $rows = $st->fetchAll();

    $out=[];
    foreach($rows as $r){
      $out[] = [
        'id'            => (int)$r['id'],
        'kind'          => $r['kind'], // image|figma
        'title'         => $r['title'],
        'desc'          => $r['description'],
        'category'      => $r['category'],
        'visibility'    => $r['visibility'],
        'allowComments' => (bool)$r['allow_comments'],
        'allowDownload' => (bool)$r['allow_download'],
        'status'        => $r['status'],
        'scheduledAt'   => $r['scheduled_at'],
        'publishedAt'   => $r['published_at'],
        'likesCount'    => (int)$r['likes_count'],
        'savesCount'    => (int)$r['saves_count'],
        'commentsCount' => (int)$r['comments_count'],
        'figmaUrl'      => $r['figma_url'],
        'media'         => media_url($r['media_path']),
        'createdAt'     => $r['created_at'],
        'updatedAt'     => $r['updated_at'] ?? null,
      ];
    }

    json_ok(['data'=>$out,'meta'=>add_pagination_meta($total,$page,$per)]);
  }

  public static function saved(): void {
    $uid = require_auth();
    global $pdo;
    [$page,$per,$offset] = page_params();

    $w = [
      "s.user_id = ?",
      "(d.visibility = 'public' OR d.owner_id = ?)",
      "d.status = 'published'"
    ];
    $p = [$uid,$uid];

    if (!empty($_GET['category'])) {
      $w[] = 'd.category = ?'; $p[] = strtolower($_GET['category']);
    }

    $order = 'd.published_at DESC';
    $sort = $_GET['sort'] ?? 'newest';
    if ($sort==='oldest') $order='d.published_at ASC';
    if ($sort==='likes')  $order='d.likes_count DESC, d.published_at DESC';
    if ($sort==='saves')  $order='d.saves_count DESC, d.published_at DESC';

    $sqlCount = 'SELECT COUNT(*) FROM design_saves s JOIN designs d ON d.id=s.design_id WHERE '.implode(' AND ',$w);
    $stc = $pdo->prepare($sqlCount); $stc->execute($p);
    $total = (int)$stc->fetchColumn();

    $sql = 'SELECT d.* FROM design_saves s JOIN designs d ON d.id=s.design_id
            WHERE '.implode(' AND ', $w).'
            ORDER BY '.$order.' LIMIT ? OFFSET ?';
    $st = $pdo->prepare($sql);
    $st->execute(array_merge($p, [$per, $offset]));
    $rows = $st->fetchAll();

    $out=[];
    foreach($rows as $r){
      $out[] = [
        'id'         => (int)$r['id'],
        'kind'       => $r['kind'],
        'title'      => $r['title'],
        'desc'       => $r['description'],
        'category'   => $r['category'],
        'media'      => media_url($r['media_path']),
        'figmaUrl'   => $r['figma_url'],
        'likesCount' => (int)$r['likes_count'],
        'savesCount' => (int)$r['saves_count'],
        'publishedAt'=> $r['published_at'],
      ];
    }
    json_ok(['data'=>$out,'meta'=>add_pagination_meta($total,$page,$per)]);
  }
}
