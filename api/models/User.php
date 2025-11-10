<?php
declare(strict_types=1);

require_once __DIR__.'/../config.php';

class User {
  public static function findByEmail(?string $email): ?array {
    if (!$email) return null;
    global $pdo;
    $st = $pdo->prepare('SELECT id,email,name,avatar,avatar_url,created_at FROM users WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    $row = $st->fetch();
    return $row ?: null;
  }

  public static function findById(int $id): ?array {
    global $pdo;
    $st = $pdo->prepare('SELECT id,email,name,avatar,avatar_url,created_at FROM users WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row ?: null;
  }

  /** Login sederhana: buat user jika belum ada (tanpa password). */
  public static function findOrCreateByEmail(string $email, string $name = ''): int {
    global $pdo;
    $u = self::findByEmail($email);
    if ($u) return (int)$u['id'];

    $name = trim($name) !== '' ? trim($name) : (explode('@',$email)[0] ?? 'Pengguna');
    $st = $pdo->prepare('INSERT INTO users (email,name,created_at) VALUES (?,?,NOW())');
    $st->execute([$email,$name]);
    return (int)$pdo->lastInsertId();
  }

  public static function updateBasic(int $id, array $data): void {
    global $pdo;
    $fields=[]; $params=[':id'=>$id];
    if (isset($data['name']))       { $fields[]='name=:name';         $params[':name']=trim((string)$data['name']); }
    if (isset($data['avatar_url'])) { $fields[]='avatar_url=:avatar'; $params[':avatar']=(string)$data['avatar_url']; }
    if (isset($data['avatar']))     { $fields[]='avatar=:avatarf';    $params[':avatarf']=(string)$data['avatar']; }
    if (!$fields) return;
    $sql = 'UPDATE users SET '.implode(', ',$fields).' WHERE id=:id';
    $pdo->prepare($sql)->execute($params);
  }
}
