<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('METHOD_NOT_ALLOWED', 405);

$uid = require_auth();
$design_id = (int)($_POST['design_id'] ?? 0);
if ($design_id <= 0) json_err('MISSING_ID', 422);

$db = db();
$st = $db->prepare("SELECT owner_id, visibility FROM designs WHERE id=:id LIMIT 1");
$st->execute([':id'=>$design_id]);
$d = $st->fetch();
if (!$d) json_err('DESIGN_NOT_FOUND', 404);
if ($d['visibility'] !== 'public' && (int)$d['owner_id'] !== $uid) json_err('FORBIDDEN', 403);

$st = $db->prepare("SELECT 1 FROM design_saves WHERE design_id=:d AND user_id=:u");
$st->execute([':d'=>$design_id, ':u'=>$uid]);
$has = (bool)$st->fetchColumn();

if ($has) {
  $db->prepare("DELETE FROM design_saves WHERE design_id=:d AND user_id=:u")
     ->execute([':d'=>$design_id, ':u'=>$uid]);
  $saved = false;
} else {
  $db->prepare("INSERT INTO design_saves(design_id,user_id) VALUES(:d,:u)")
     ->execute([':d'=>$design_id, ':u'=>$uid]);
  $saved = true;
}
$count = (int)$db->query("SELECT COUNT(*) FROM design_saves WHERE design_id=".$design_id)->fetchColumn();

json_ok(['saved'=>$saved,'saves_count'=>$count]);
