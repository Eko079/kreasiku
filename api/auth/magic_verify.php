<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/Response.php';

$token = trim((string)($_GET['token'] ?? $_POST['token'] ?? ''));
if ($token === '') {
  render_message('Token tidak ditemukan atau sudah kadaluarsa.');
  exit;
}

try {
  $result = verify_token($token);
  if (!$result) {
    render_message('Token tidak valid atau sudah kadaluarsa.');
    exit;
  }
  $_SESSION['user_id'] = (int)$result['user_id'];
  mark_token_used($result['id']);
  $user = fetch_user((int)$result['user_id']);
  render_message('Login berhasil. Mengalihkan...', 'pages/account/Account.html', $user);
} catch (Throwable $e) {
  render_message('Terjadi kesalahan: '.$e->getMessage());
}

function verify_token(string $token): ?array {
  $db = db();
  $hash = hash('sha256', $token);
  $st = $db->prepare('SELECT * FROM magic_tokens WHERE token_hash=:hash LIMIT 1');
  $st->execute([':hash'=>$hash]);
  $row = $st->fetch();
  if (!$row) return null;
  if ($row['used_at']) return null;
  if (strtotime((string)$row['expires_at']) < time()) return null;
  return $row;
}

function mark_token_used(int $id): void {
  $db = db();
  $up = $db->prepare('UPDATE magic_tokens SET used_at=NOW() WHERE id=:id');
  $up->execute([':id'=>$id]);
}

function render_message(string $message, string $redirect = '', array $userData = []): void {
  $base = SITE_ROOT();
  $target = ($redirect !== '' && $base !== '') ? $base . '/' . ltrim($redirect, '/') : '';
  header('Content-Type: text/html; charset=UTF-8');
  if ($target !== '') {
    header('Refresh: 2; url=' . $target);
  }
  echo '<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Magic Link</title>';
  if ($target !== '') {
    echo '<meta http-equiv="refresh" content="2;url=' . htmlspecialchars($target, ENT_QUOTES) . '">';
  }
  echo '<style>body{font-family:Poppins,Arial,sans-serif;background:#f5f6fb;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;} .box{background:#fff;padding:32px;border-radius:16px;box-shadow:0 10px 30px rgba(36,44,92,.15);text-align:center;width:320px;} </style></head><body><div class="box"><p>' . htmlspecialchars($message, ENT_QUOTES) . '</p>';
  if ($target !== '') {
    echo '<p>Jika tidak diarahkan, klik <a href="' . htmlspecialchars($target, ENT_QUOTES) . '">di sini</a>.</p>';
  }
  if ($userData) {
    $json = json_encode($userData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
    if ($json !== false) {
      echo '<script>try{localStorage.setItem("authUser", JSON.stringify(' . $json . '));}catch(e){}</script>';
    }
  }
  echo '</div></body></html>';
}

function SITE_ROOT(): string {
  $api = base_url(); // contoh http://localhost/api/auth
  $root = rtrim(dirname(dirname($api)), '/'); // http://localhost
  return $root === '' ? '/' : $root;
}

function fetch_user(int $id): array {
  $db = db();
  $st = $db->prepare('SELECT id,email,name,avatar,avatar_url,created_at FROM users WHERE id=:id LIMIT 1');
  $st->execute([':id'=>$id]);
  $row = $st->fetch();
  return $row ?: ['id'=>$id];
}
