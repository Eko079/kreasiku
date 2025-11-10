<?php
declare(strict_types=1);

require_once __DIR__.'/../utils/Response.php';
require_once __DIR__.'/../utils/Pagination.php';
require_once __DIR__.'/../models/Design.php';
require_once __DIR__.'/../config.php';

class SearchController {
  /** GET /search?q=&category=&sort=&page=&perPage=  (publik & published saja) */
  public static function query(): void {
    global $pdo;
    [$page,$per,$offset] = page_params();
    $q   = trim((string)($_GET['q'] ?? ''));
    $cat = isset($_GET['category']) ? strtolower(trim($_GET['category'])) : null;

    $w = ["status='published'","visibility='public'"];
    $p = [];

    if ($q!=='') {
      $w[] = "(title LIKE ? OR description LIKE ?)";
      $p[] = '%'.$q.'%'; $p[]='%'.$q.'%';
    }
    if ($cat) { $w[] = "category = ?"; $p[] = $cat; }

    $order='published_at DESC';
    $sort = $_GET['sort'] ?? 'newest';
    if ($sort==='oldest') $order='published_at ASC';
    if ($sort==='likes')  $order='likes_count DESC, published_at DESC';
    if ($sort==='saves')  $order='saves_count DESC, published_at DESC';

    $st = $pdo->prepare('SELECT COUNT(*) FROM designs WHERE '.implode(' AND ',$w));
    $st->execute($p);
    $total = (int)$st->fetchColumn();

    $sql = 'SELECT * FROM designs WHERE '.implode(' AND ',$w).' ORDER BY '.$order.' LIMIT ? OFFSET ?';
    $st  = $pdo->prepare($sql);
    $st->execute(array_merge($p, [$per, $offset]));
    $rows = $st->fetchAll();

    $out=[];
    foreach($rows as $r){
      $imgs = Design::getImages((int)$r['id']);
      $out[] = [
        'id'=>(int)$r['id'],
        'type'=>$r['type'],
        'title'=>$r['title'],
        'desc'=>$r['description'],
        'category'=>$r['category'],
        'images'=>$imgs,
        'figmaUrl'=>$r['figma_url'],
        'likesCount'=>(int)$r['likes_count'],
        'savesCount'=>(int)$r['saves_count'],
        'publishedAt'=>$r['published_at'],
      ];
    }
    json_ok(['data'=>$out,'meta'=>add_pagination_meta($total,$page,$per)]);
  }
}
