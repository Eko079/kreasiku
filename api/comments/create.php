<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('METHOD_NOT_ALLOWED', 405);

$uid       = require_auth();
$design_id = (int)($_POST['design_id'] ?? 0);
// Accept multiple possible field names from clients: 'body', 'text', or 'content'
$body      = trim((string)($_POST['body'] ?? $_POST['text'] ?? $_POST['content'] ?? ''));

if ($design_id <= 0 || $body === '') json_err('MISSING_FIELDS', 422);

$db = db();
try {
  $st = $db->prepare('SELECT id, owner_id, allow_comments, visibility FROM designs WHERE id=:id LIMIT 1');
  $st->execute([':id'=>$design_id]);
  $d = $st->fetch();
  if (!$d) json_err('DESIGN_NOT_FOUND', 404);
  if ((int)$d['allow_comments'] !== 1) json_err('COMMENTS_DISABLED', 403);
  if ($d['visibility'] === 'private' && (int)$d['owner_id'] !== $uid) json_err('FORBIDDEN', 403);

  $ins = $db->prepare('INSERT INTO comments (design_id,user_id,body,created_at) VALUES (:d,:u,:b,NOW())');
  $ins->execute([':d'=>$design_id, ':u'=>$uid, ':b'=>$body]);

  $db->prepare('UPDATE designs SET comments_count = comments_count + 1 WHERE id=:id')->execute([':id'=>$design_id]);

  json_ok(['id'=>(int)$db->lastInsertId(), 'design_id'=>$design_id, 'body'=>$body]);
} catch (Throwable $e) {
  json_err('DB_ERROR: '.$e->getMessage(), 500);
}
