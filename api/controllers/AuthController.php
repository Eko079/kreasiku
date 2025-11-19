<?php
declare(strict_types=1);

require_once __DIR__.'/../utils/Response.php';
require_once __DIR__.'/../config.php'; // pakai $pdo & session

class AuthController {
  public static function register(): void {
    // Sama perilakunya dengan login magic email: create-if-not-exists
    $in = require_json();
    $email = trim((string)($in['email'] ?? ''));
    $name  = trim((string)($in['name'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      json_err('INVALID_EMAIL', 422);
    }
    global $pdo;
    $pdo->beginTransaction();
    try {
      $st = $pdo->prepare('SELECT id,email,name,avatar,created_at FROM users WHERE email=? LIMIT 1');
      $st->execute([$email]);
      $u = $st->fetch();
      if (!$u) {
        $st = $pdo->prepare('INSERT INTO users(email,name,created_at) VALUES(?,?,NOW())');
        $st->execute([$email, $name !== '' ? $name : explode('@',$email)[0]]);
        $id = (int)$pdo->lastInsertId();
        $st = $pdo->prepare('SELECT id,email,name,avatar,created_at FROM users WHERE id=?');
        $st->execute([$id]); $u = $st->fetch();
      }
      $_SESSION['user_id'] = (int)$u['id'];
      $pdo->commit();
      json_ok(['user'=>$u], 201);
    } catch (\Throwable $e) { $pdo->rollBack(); throw $e; }
  }

  public static function login(): void {
    $in = require_json();
    $email = trim((string)($in['email'] ?? ''));
    $name  = trim((string)($in['name'] ?? ''));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
      json_err('INVALID_EMAIL', 422);
    }
    global $pdo;
    $pdo->beginTransaction();
    try {
      $st = $pdo->prepare('SELECT id,email,name,avatar,created_at FROM users WHERE email=? LIMIT 1');
      $st->execute([$email]);
      $u = $st->fetch();
      if (!$u) {
        $st = $pdo->prepare('INSERT INTO users(email,name,created_at) VALUES(?,?,NOW())');
        $st->execute([$email, $name !== '' ? $name : explode('@',$email)[0]]);
        $id = (int)$pdo->lastInsertId();
        $st = $pdo->prepare('SELECT id,email,name,avatar,created_at FROM users WHERE id=?');
        $st->execute([$id]); $u = $st->fetch();
      }
      $_SESSION['user_id'] = (int)$u['id'];
      $pdo->commit();
      json_ok(['user'=>$u]);
    } catch (\Throwable $e) { $pdo->rollBack(); throw $e; }
  }

  public static function me(): void {
    if (!isset($_SESSION['user_id'])) json_ok(['user'=>null]);
    global $pdo;
    $st = $pdo->prepare('SELECT id,email,name,avatar,created_at FROM users WHERE id=?');
    $st->execute([(int)$_SESSION['user_id']]);
    $u = $st->fetch();
    json_ok(['user'=>$u ?: null]);
  }

  public static function logout(): void {
    session_destroy();
    json_ok(['message'=>'LOGGED_OUT']);
  }
}
