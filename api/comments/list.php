<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/Response.php';

$design_id = (int)($_GET['design_id'] ?? 0);
$page  = max(1, (int)($_GET['page'] ?? 1));
$limit = min(100, max(1, (int)($_GET['limit'] ?? 20)));
$offset = ($page - 1) * $limit;

if ($design_id <= 0) json_err('MISSING_DESIGN_ID', 422);

$db = db();
try {
  $q = $db->prepare('SELECT c.id, c.body, c.created_at,
                            u.id AS user_id, u.name, u.email, u.avatar_url
                       FROM comments c
                       JOIN users u ON u.id = c.user_id
                      WHERE c.design_id = :d
                   ORDER BY c.id ASC
                      LIMIT :lim OFFSET :off');
  $q->bindValue(':d', $design_id, PDO::PARAM_INT);
  $q->bindValue(':lim', $limit, PDO::PARAM_INT);
  $q->bindValue(':off', $offset, PDO::PARAM_INT);
  $q->execute();
  $items = $q->fetchAll();

  $c = $db->prepare('SELECT COUNT(*) AS n FROM comments WHERE design_id=:d');
  $c->execute([':d'=>$design_id]);
  $total = (int)$c->fetch()['n'];

  json_ok(['items'=>$items, 'total'=>$total, 'page'=>$page, 'limit'=>$limit]);
} catch (Throwable $e) {
  json_err('DB_ERROR: '.$e->getMessage(), 500);
}
