<?php
declare(strict_types=1);

require_once __DIR__ . '/Response.php';
require_once __DIR__ . '/../config.php';

/** Ambil user_id aktif dari session (atau null) */
function current_user_id(): ?int {
  if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
  return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

/** Wajib login: kalau belum login -> 401 */
function require_auth(): int {
  $uid = current_user_id();
  if (!$uid) json_err('UNAUTHENTICATED', 401);
  return $uid;
}

/** Login opsional: boleh null */
function optional_auth(): ?int {
  return current_user_id();
}

/** Cari atau buat user berdasarkan email; kembalikan id */
function find_or_create_user(PDO $pdo, string $email, string $name, ?string $avatar = null): int {
  $st = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
  $st->execute([':email' => $email]);
  $row = $st->fetch();
  if ($row) {
    $upd = $pdo->prepare("UPDATE users SET name = :name, avatar = :avatar WHERE id = :id");
    $upd->execute([':name' => $name, ':avatar' => $avatar, ':id' => $row['id']]);
    return (int)$row['id'];
  }
  $ins = $pdo->prepare(
    "INSERT INTO users (email, name, avatar, created_at) VALUES (:email, :name, :avatar, NOW())"
  );
  $ins->execute([':email' => $email, ':name' => $name, ':avatar' => $avatar]);
  return (int)$pdo->lastInsertId();
}

/** Set session login */
function login_user(int $user_id): void {
  if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
  $_SESSION['user_id'] = $user_id;
}

/** Hapus session login */
function logout_user(): void {
  if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
  unset($_SESSION['user_id']);
}
