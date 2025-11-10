<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/Auth.php';

$uid = require_auth();

// Determine comment id from multiple possible sources:
// - form-data (POST)
// - query string (GET)
// - JSON request body (application/json)
$comment_id = 0;

$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
$jsonBody = null;
if (stripos($contentType, 'application/json') !== false) {
  $raw = file_get_contents('php://input');
  $jsonBody = json_decode($raw, true);
}

$candidates = ['id', 'comment_id', 'commentId'];
foreach ($candidates as $key) {
  if (isset($_POST[$key])) { $comment_id = (int)$_POST[$key]; break; }
  if (isset($_GET[$key]))  { $comment_id = (int)$_GET[$key]; break; }
  if (is_array($jsonBody) && array_key_exists($key, $jsonBody)) { $comment_id = (int)$jsonBody[$key]; break; }
}

if ($comment_id <= 0) json_err('MISSING_ID', 422);

$db = db();
try {
  $st = $db->prepare('SELECT c.id, c.user_id, c.design_id, d.owner_id
                        FROM comments c
                        JOIN designs d ON d.id = c.design_id
                       WHERE c.id = :id');
  $st->execute([':id'=>$comment_id]);
  $row = $st->fetch();
  if (!$row) json_err('NOT_FOUND', 404);

  if ($uid !== (int)$row['user_id'] && $uid !== (int)$row['owner_id']) {
    json_err('FORBIDDEN', 403);
  }

  $db->prepare('DELETE FROM comments WHERE id=:id')->execute([':id'=>$comment_id]);
  $db->prepare('UPDATE designs SET comments_count = GREATEST(comments_count-1,0) WHERE id=:id')->execute([':id'=>$row['design_id']]);

  json_ok(['deleted'=>true]);
} catch (Throwable $e) {
  json_err('DB_ERROR: '.$e->getMessage(), 500);
}
