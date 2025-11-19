<?php
declare(strict_types=1);

require_once __DIR__.'/../utils/Response.php';
require_once __DIR__.'/../config.php';

class FeedController {
  public static function trending(): void {
    global $pdo;
    $sort = $_GET['sort'] ?? 'likes';
    $days = max(1, min(60, (int)($_GET['days'] ?? 7)));
    $cat  = isset($_GET['category']) ? strtolower(trim($_GET['category'])) : null;

    $orderCol = $sort==='saves' ? 'saves_count' : 'likes_count';

    $w = ["status='published'","visibility='public'","published_at >= (NOW() - INTERVAL {$days} DAY)"];
    $p = [];
    if ($cat) { $w[] = 'category = ?'; $p[] = $cat; }

    $sql = 'SELECT id,kind,title,description,category,figma_url,media_path,likes_count,saves_count,published_at
            FROM designs WHERE '.implode(' AND ',$w).' ORDER BY '.$orderCol.' DESC, published_at DESC LIMIT 60';
    $st  = $pdo->prepare($sql); $st->execute($p);
    $rows = $st->fetchAll();

    $out=[];
    foreach($rows as $r){
      $out[] = [
        'id'=>(int)$r['id'],
        'kind'=>$r['kind'],
        'title'=>$r['title'],
        'desc'=>$r['description'],
        'category'=>$r['category'],
        'media' => $r['media_path'] ? (rtrim(base_url(),'/').'/storage/'.ltrim($r['media_path'],'/')) : null,
        'figmaUrl'=>$r['figma_url'],
        'likesCount'=>(int)$r['likes_count'],
        'savesCount'=>(int)$r['saves_count'],
        'publishedAt'=>$r['published_at'],
      ];
    }
    json_ok(['data'=>$out]);
  }
}
