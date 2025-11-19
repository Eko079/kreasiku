<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/Response.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_err('METHOD_NOT_ALLOWED', 405);
}

if (!defined('GOOGLE_CLIENT_ID') || GOOGLE_CLIENT_ID === '' || GOOGLE_CLIENT_ID === 'YOUR_GOOGLE_CLIENT_ID') {
  json_err('GOOGLE_CLIENT_ID_NOT_SET', 500, ['detail' => 'Set GOOGLE_CLIENT_ID di api/config.php']);
}

$input = json_decode(file_get_contents('php://input') ?: '[]', true);
$accessToken = trim((string)($input['access_token'] ?? ''));
if ($accessToken === '') {
  json_err('TOKEN_REQUIRED', 422);
}

$userInfo = fetch_google_profile($accessToken);
if (!$userInfo) {
  json_err('GOOGLE_TOKEN_INVALID', 401);
}

if (($userInfo['aud'] ?? '') !== GOOGLE_CLIENT_ID) {
  json_err('GOOGLE_CLIENT_ID_MISMATCH', 401);
}

$email = strtolower(trim((string)($userInfo['email'] ?? '')));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  json_err('EMAIL_NOT_VERIFIED', 400);
}

$name  = trim((string)($userInfo['name'] ?? ''));
$avatar= trim((string)($userInfo['picture'] ?? ''));

$db = db();
$db->beginTransaction();
try {
  $st = $db->prepare('SELECT id,email,name,avatar,avatar_url,created_at FROM users WHERE email=:email LIMIT 1');
  $st->execute([':email'=>$email]);
  $user = $st->fetch();

  if (!$user) {
    $ins = $db->prepare('INSERT INTO users (email,name,avatar_url,created_at) VALUES (:email,:name,:avatar_url,NOW())');
    $ins->execute([
      ':email'=>$email,
      ':name'=>($name!=='' ? $name : null),
      ':avatar_url'=>($avatar!=='' ? $avatar : null),
    ]);
    $uid = (int)$db->lastInsertId();
    $st = $db->prepare('SELECT id,email,name,avatar,avatar_url,created_at FROM users WHERE id=:id');
    $st->execute([':id'=>$uid]);
    $user = $st->fetch();
  } else {
    $needName = ($name !== '') && empty($user['name']);
    $needAvatar = ($avatar !== '') && empty($user['avatar_url']);
    if ($needName || $needAvatar) {
      $upd = $db->prepare('UPDATE users SET name=COALESCE(:name,name), avatar_url=COALESCE(:avatar_url,avatar_url) WHERE id=:id');
      $upd->execute([
        ':name' => $needName ? $name : null,
        ':avatar_url' => $needAvatar ? $avatar : null,
        ':id' => $user['id'],
      ]);
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

function fetch_google_profile(string $token): ?array {
  $userinfo = http_json('https://openidconnect.googleapis.com/v1/userinfo', [
    CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $token],
  ]);
  if (!$userinfo) return null;

  $tokenInfo = http_json('https://oauth2.googleapis.com/tokeninfo?access_token=' . urlencode($token));
  if (!$tokenInfo) return null;

  return array_merge($userinfo, $tokenInfo);
}

function http_json(string $url, array $extraOpts = []): ?array {
  $ch = curl_init($url);
  $options = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 10,
  ] + $extraOpts;
  curl_setopt_array($ch, $options);
  $resp = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
  curl_close($ch);
  if ($resp === false || $code !== 200) return null;
  $data = json_decode($resp, true);
  return is_array($data) ? $data : null;
}
