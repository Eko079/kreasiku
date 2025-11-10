<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('METHOD_NOT_ALLOWED', 405);

$raw = file_get_contents('php://input');
$in  = json_decode($raw, true) ?: [];

$email      = trim((string)($in['email'] ?? ''));
$name       = trim((string)($in['name'] ?? ''));
$avatar_url = trim((string)($in['avatar_url'] ?? ''));

if ($email === '') json_err('EMAIL_REQUIRED', 422);

$db = db();
$db->beginTransaction();
try {
  // find or create
  $st = $db->prepare('SELECT id,email,name,avatar,avatar_url,created_at FROM users WHERE email=:email LIMIT 1');
  $st->execute([':email'=>$email]);
  $user = $st->fetch();

  if (!$user) {
    $st = $db->prepare('INSERT INTO users (email,name,avatar_url,created_at) VALUES (:email,:name,:avatar_url,NOW())');
    $st->execute([
      ':email'=>$email,
      ':name'=>($name!=='' ? $name : null),
      ':avatar_url'=>($avatar_url!=='' ? $avatar_url : null),
    ]);
    $uid = (int)$db->lastInsertId();
    $st = $db->prepare('SELECT id,email,name,avatar,avatar_url,created_at FROM users WHERE id=:id');
    $st->execute([':id'=>$uid]);
    $user = $st->fetch();
  } else {
    if ($name!=='' || $avatar_url!=='') {
      $upd = $db->prepare('UPDATE users SET name=COALESCE(:name,name), avatar_url=COALESCE(:avatar_url,avatar_url) WHERE id=:id');
      $upd->execute([':name'=>($name!==''?$name:null), ':avatar_url'=>($avatar_url!==''?$avatar_url:null), ':id'=>$user['id']]);
      $st = $db->prepare('SELECT id,email,name,avatar,avatar_url,created_at FROM users WHERE id=:id');
      $st->execute([':id'=>$user['id']]);
      $user = $st->fetch();
    }
  }

  $_SESSION['user_id'] = (int)$user['id'];
  $db->commit();
  json_ok(['user'=>$user]);
} catch (Throwable $e) {
  $db->rollBack();
  json_err('DB_ERROR: '.$e->getMessage(), 500);
}
