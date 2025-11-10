<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Auth.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) json_err('MISSING_ID', 422);

$uid = optional_auth();
$db  = db();

/** ambil desain + owner + counters + status saya (liked/saved) */
$sql = "
SELECT
  d.id, d.owner_id, d.category, d.kind, d.media_path, d.figma_url,
  d.title, d.description, d.allow_comments, d.allow_download, d.visibility, d.created_at,
  u.name AS owner_name, u.avatar AS owner_avatar,
  (SELECT COUNT(*) FROM design_likes  l WHERE l.design_id=d.id) AS likes_count,
  (SELECT COUNT(*) FROM design_saves  s WHERE s.design_id=d.id) AS saves_count,
  (SELECT COUNT(*) FROM comments      c WHERE c.design_id=d.id) AS comments_count,
  ".($uid ? "EXISTS(SELECT 1 FROM design_likes  l2 WHERE l2.design_id=d.id AND l2.user_id=:uid) AS liked," : "0 AS liked,")."
  ".($uid ? "EXISTS(SELECT 1 FROM design_saves  s2 WHERE s2.design_id=d.id AND s2.user_id=:uid2) AS saved" : "0 AS saved")."
FROM designs d
JOIN users u ON u.id=d.owner_id
WHERE d.id=:id AND (d.visibility='public' OR d.owner_id=".($uid?:0).")
LIMIT 1";
$st = $db->prepare($sql);
$params = [':id'=>$id];
if ($uid) { $params[':uid']=$uid; $params[':uid2']=$uid; }
$st->execute($params);
$row = $st->fetch();
if (!$row) json_err('NOT_FOUND', 404);

json_ok($row);
