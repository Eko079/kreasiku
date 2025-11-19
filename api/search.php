<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/utils/Auth.php'; // optional_auth()

function media_public_url(?string $path): ?string {
  if (!$path) return null;
  $clean = ltrim(str_replace('\\','/', (string)$path), '/');
  if ($clean === '') return null;
  $apiBase  = rtrim(base_url(), '/');
  $rootBase = rtrim(dirname($apiBase), '/');
  if (str_starts_with($clean, 'storage/')) return $rootBase . '/' . $clean;
  return $rootBase . '/storage/' . $clean;
}

header('Content-Type: application/json');

/**
 * Query params:
 *   q          : string (pencarian judul/desc)
 *   category   : 'portofolio'|'website'|'cv'|'desain'|'logo'|'' (semua)
 *   sort       : 'relevant'|'newest'|'oldest'|'likes'|'stars'
 *   download   : 'allowed'|'blocked'|''
 *   page       : int (default 1)
 *   per_page   : int (default 12, max 60)
 */
$q        = trim($_GET['q'] ?? '');
$cat      = strtolower(trim($_GET['category'] ?? ''));
$sort     = strtolower(trim($_GET['sort'] ?? 'relevant'));
$dl       = strtolower(trim($_GET['download'] ?? ''));
$page     = max(1, (int)($_GET['page'] ?? 1));
$perPage  = min(60, max(1, (int)($_GET['per_page'] ?? 12)));
$offset   = ($page - 1) * $perPage;

$w = [];
$p = [];

/** hanya tampilkan yang sudah publish & public */
$w[] = "d.status = 'published' AND d.visibility = 'public'";
$w[] = "(d.published_at IS NULL OR d.published_at <= NOW())";

/** filter kategori */
if ($cat !== '') {
  $w[] = "d.category = :cat";
  $p[':cat'] = $cat;
}

/** filter download */
if ($dl === 'allowed') {
  $w[] = "d.allow_download = 1";
} elseif ($dl === 'blocked') {
  $w[] = "d.allow_download = 0";
}

/** pencarian sederhana di title/description */
$rankExpr = '0';
if ($q !== '') {
  $w[] = "(d.title LIKE :like OR d.description LIKE :like OR d.category LIKE :like OR d.figma_url LIKE :like)";
  $p[':like'] = '%'.$q.'%';
  $rankExpr = "(CASE WHEN d.title LIKE :like THEN 4 WHEN d.category LIKE :like THEN 3 WHEN d.description LIKE :like THEN 2 WHEN d.figma_url LIKE :like THEN 1 ELSE 0 END)";
}

$where = $w ? ('WHERE '.implode(' AND ', $w)) : '';

/** sort */
$order = "d.updated_at DESC";
if ($sort === 'newest')  $order = "d.created_at DESC";
if ($sort === 'oldest')  $order = "d.created_at ASC";
if ($sort === 'likes')   $order = "likes_cnt DESC, d.created_at DESC";
if ($sort === 'stars')   $order = "saves_cnt DESC, d.created_at DESC";
if ($sort === 'relevant' && $q !== '') $order = "$rankExpr DESC, d.created_at DESC";

/** hitung total */
$countSql = "SELECT COUNT(*) AS c
             FROM designs d
             $where";
$stc = db()->prepare($countSql);
$stc->execute($p);
$total = (int)$stc->fetchColumn();

/** ambil data */
$sql = "
SELECT
  d.id, d.title, d.description, d.category, d.kind,
  d.media_path, d.figma_url, d.allow_download, d.allow_comments,
  d.created_at, d.updated_at,
  COALESCE(l.cnt,0) AS likes_cnt,
  COALESCE(s.cnt,0) AS saves_cnt
FROM designs d
LEFT JOIN (SELECT design_id, COUNT(*) AS cnt FROM design_likes GROUP BY design_id) l
       ON l.design_id = d.id
LEFT JOIN (SELECT design_id, COUNT(*) AS cnt FROM design_saves GROUP BY design_id) s
       ON s.design_id = d.id
$where
ORDER BY $order
LIMIT :limit OFFSET :offset";
$st = db()->prepare($sql);
foreach ($p as $k=>$v) { $st->bindValue($k, $v); }
$st->bindValue(':limit',  $perPage, PDO::PARAM_INT);
$st->bindValue(':offset', $offset,  PDO::PARAM_INT);
$st->execute();

$base = base_url(); // .../api
$items = [];
while ($r = $st->fetch()) {
  $mediaUrl = null;
  if ($r['kind'] === 'image') {
    $mediaUrl = media_public_url($r['media_path'] ?? null);
  }
  $items[] = [
    'id'             => (int)$r['id'],
    'title'          => $r['title'],
    'description'    => $r['description'],
    'category'       => $r['category'],
    'kind'           => $r['kind'],
    'media_url'      => $mediaUrl,
    'figma_url'      => $r['figma_url'],
    'allow_download' => (int)$r['allow_download'],
    'download_url'   => ((int)$r['allow_download']) ? $base . '/designs/' . (int)$r['id'] . '/download' : null,
    'allow_comments' => (int)$r['allow_comments'],
    'created_at'     => $r['created_at'],
    'updated_at'     => $r['updated_at'],
    'likes'          => (int)$r['likes_cnt'],
    'saves'          => (int)$r['saves_cnt'],
  ];
}

json_ok([
  'items'     => $items,
  'total'     => $total,
  'page'      => $page,
  'per_page'  => $perPage,
]);
