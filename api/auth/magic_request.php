<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Mailer.php';
require_once __DIR__ . '/../utils/EmailUtils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_err('METHOD_NOT_ALLOWED', 405);
}

$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
$email = strtolower(trim((string)($payload['email'] ?? '')));
$name  = trim((string)($payload['name'] ?? ''));

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  json_err('INVALID_EMAIL', 422);
}

if (is_disposable_email($email)) {
  json_err('DISPOSABLE_EMAIL_BLOCKED', 422);
}

$db = db();
ensure_magic_table($db);

$db->beginTransaction();
try {
  $user = find_or_create_user($db, $email, $name);
  $token = bin2hex(random_bytes(32));
  $hash  = hash('sha256', $token);
  $expires = date('Y-m-d H:i:s', time() + 15 * 60);

  $ins = $db->prepare('INSERT INTO magic_tokens (user_id,email,token_hash,expires_at,request_ip,user_agent,created_at) VALUES (:uid,:email,:hash,:exp,:ip,:agent,NOW())');
  $ins->execute([
    ':uid'   => $user['id'],
    ':email' => $email,
    ':hash'  => $hash,
    ':exp'   => $expires,
    ':ip'    => get_client_ip(),
    ':agent' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255),
  ]);

  $link = build_magic_link($token);
  if (!mailer_send($email, 'Magic Link Login', "Klik tautan berikut untuk login:\n$link\n\nLink berlaku 15 menit.")) {
    throw new RuntimeException('EMAIL_SEND_FAILED');
  }

  $response = ['message' => 'MAGIC_LINK_SENT'];
  if (is_localhost()) {
    $response['debug_link'] = $link;
  }

  $db->commit();
  json_ok($response);
} catch (Throwable $e) {
  $db->rollBack();
  json_err('SERVER_ERROR', 500, ['detail' => $e->getMessage()]);
}

function ensure_magic_table(PDO $db): void {
  $db->exec('CREATE TABLE IF NOT EXISTS magic_tokens (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(190) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    request_ip VARCHAR(64) DEFAULT NULL,
    user_agent VARCHAR(255) DEFAULT NULL,
    used_at DATETIME DEFAULT NULL,
    created_at DATETIME NOT NULL,
    INDEX token_hash_idx (token_hash),
    INDEX expires_idx (expires_at),
    CONSTRAINT fk_magic_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;');

  try {
    $db->exec('ALTER TABLE magic_tokens ADD COLUMN request_ip VARCHAR(64) DEFAULT NULL');
  } catch (Throwable $e) {}
  try {
    $db->exec('ALTER TABLE magic_tokens ADD COLUMN user_agent VARCHAR(255) DEFAULT NULL');
  } catch (Throwable $e) {}
}

function find_or_create_user(PDO $db, string $email, string $name): array {
  $st = $db->prepare('SELECT id,email,name,avatar,avatar_url,created_at FROM users WHERE email=:email LIMIT 1');
  $st->execute([':email' => $email]);
  $user = $st->fetch();
  if ($user) return $user;

  $ins = $db->prepare('INSERT INTO users (email,name,created_at) VALUES (:email,:name,NOW())');
  $ins->execute([
    ':email' => $email,
    ':name'  => ($name !== '' ? $name : null),
  ]);
  $id = (int)$db->lastInsertId();
  $st = $db->prepare('SELECT id,email,name,avatar,avatar_url,created_at FROM users WHERE id=:id');
  $st->execute([':id' => $id]);
  return $st->fetch();
}

function build_magic_link(string $token): string {
  $base = base_url(); // contoh: http://localhost/api/auth
  $apiBase = rtrim(dirname($base), '/'); // jadi http://localhost/api
  return $apiBase . '/auth/magic_verify.php?token=' . urlencode($token);
}

function is_localhost(): bool {
  $host = $_SERVER['HTTP_HOST'] ?? '';
  return str_starts_with($host, 'localhost') || str_starts_with($host, '127.0.0.1');
}

function get_client_ip(): string {
  $keys = ['HTTP_CF_CONNECTING_IP','HTTP_X_FORWARDED_FOR','HTTP_X_REAL_IP','HTTP_CLIENT_IP','REMOTE_ADDR'];
  foreach ($keys as $key) {
    if (!empty($_SERVER[$key])) {
      $val = explode(',', $_SERVER[$key])[0];
      return trim($val);
    }
  }
  return '';
}
