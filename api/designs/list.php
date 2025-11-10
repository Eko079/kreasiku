<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Auth.php';

$uid     = optional_auth();
$cat     = strtolower(trim((string)($_GET['category'] ?? '')));
$limit   = max(1, min(50, (int)($_GET['limit'] ?? 12)));
$offset  = max(0, (int)($_GET['offset'] ?? 0));

$where = "(d.visibility='public' AND d.status='published')";
$bind  = [];
if ($uid) { $where = "($where OR d.owner_id=:uid)"; $bind[':uid']=$uid; }
if ($cat !== '') { $where .= " AND d.category=:cat"; $bind[':cat']=$cat; }

$sql = "
SELECT d.id, d.category, d.kind, d.media_path, d.figma_url, d.title, d.created_at,
  (SELECT COUNT(*) FROM design_likes  l WHERE l.design_id=d.id) AS likes_count,
  (SELECT COUNT(*) FROM design_saves  s WHERE s.design_id=d.id) AS saves_count
FROM designs d
WHERE $where
ORDER BY d.created_at DESC
LIMIT :lim OFFSET :off";

$db = db();
$st = $db->prepare($sql);
foreach ($bind as $k=>$v) $st->bindValue($k, $v);
$st->bindValue(':lim', $limit, PDO::PARAM_INT);
$st->bindValue(':off', $offset, PDO::PARAM_INT);
$st->execute();

json_ok(['items'=>$st->fetchAll(),'limit'=>$limit,'offset'=>$offset]);
