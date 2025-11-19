<?php
declare(strict_types=1);

require_once __DIR__.'/../utils/Response.php';
require_once __DIR__.'/../utils/Auth.php';
require_once __DIR__.'/../services/UploadService.php';
require_once __DIR__.'/../services/CascadeDelete.php';
require_once __DIR__.'/../services/StorageBin.php';
require_once __DIR__.'/../config.php';

class UsersController {
  private static bool $ensuredCols = false;

  private static function ensureColumns(): void {
    if (self::$ensuredCols) return;
    global $pdo;
    $checks = [
      'kelas'       => "ALTER TABLE users ADD COLUMN kelas VARCHAR(100) NULL AFTER name",
      'bio'         => "ALTER TABLE users ADD COLUMN bio TEXT NULL AFTER kelas",
      'avatar_path' => "ALTER TABLE users ADD COLUMN avatar_path VARCHAR(255) NULL AFTER avatar",
      'updated_at'  => "ALTER TABLE users ADD COLUMN updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at"
    ];
    foreach ($checks as $col => $sql) {
      $st = $pdo->prepare("SHOW COLUMNS FROM users LIKE ?");
      $st->execute([$col]);
      if (!$st->fetch()) {
        $pdo->exec($sql);
      }
    }
    self::$ensuredCols = true;
  }

  private static function publicPath(?string $path): ?string {
    if (!$path) return null;
    $clean = ltrim(str_replace('\\','/', (string)$path), '/');
    if ($clean === '') return null;
    $apiBase  = rtrim(base_url(), '/');
    $rootBase = rtrim(dirname($apiBase), '/');
    if (str_starts_with($clean, 'storage/')) return $rootBase.'/'.$clean;
    return $rootBase.'/storage/'.$clean;
  }

  private static function fetchUser(int $uid): array {
    self::ensureColumns();
    global $pdo;
    $st = $pdo->prepare('SELECT id,email,name,avatar,avatar_url,kelas,bio,avatar_path,created_at FROM users WHERE id=? LIMIT 1');
    $st->execute([$uid]);
    $row = $st->fetch();
    return $row ?: [];
  }

  private static function responsePayload(int $uid): array {
    $user = self::fetchUser($uid);
    $full = trim((string)($user['name'] ?? ''));
    $parts = preg_split('/\s+/', $full, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $first = $parts[0] ?? '';
    $last = count($parts) > 1 ? implode(' ', array_slice($parts, 1)) : '';
    return [
      'user' => [
        'id'        => (int)($user['id'] ?? $uid),
        'email'     => $user['email'] ?? null,
        'name'      => $full !== '' ? $full : ($user['name'] ?? null),
        'avatar'    => self::publicPath($user['avatar_path'] ?? ($user['avatar'] ?? null)) ?? ($user['avatar_url'] ?? null),
        'createdAt' => $user['created_at'] ?? null,
      ],
      'profile' => [
        'firstName' => $first,
        'lastName'  => $last,
        'kelas'     => $user['kelas'] ?? '',
        'bio'       => $user['bio'] ?? '',
        'photo'     => self::publicPath($user['avatar_path'] ?? null) ?? ($user['avatar_url'] ?? null),
        'photoPath' => $user['avatar_path'] ?? null,
      ]
    ];
  }

  private static function unlinkAvatar(?string $path): void {
    if (!$path) return;
    StorageBin::moveToBin($path);
  }

  public static function getMe(): void {
    self::ensureColumns();
    $uid = require_auth();
    json_ok(self::responsePayload($uid));
  }

  public static function updateMe(): void {
    self::ensureColumns();
    $uid = require_auth();
    $isMultipart = (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'], 'multipart/form-data'));
    $input = [];
    if ($isMultipart) {
      foreach (['firstName','lastName','kelas','bio'] as $key) {
        if (isset($_POST[$key])) $input[$key] = trim((string)$_POST[$key]);
      }
    } else {
      $input = require_json();
    }

    $fields = [];
    $first = array_key_exists('firstName', $input) ? trim((string)$input['firstName']) : null;
    $last  = array_key_exists('lastName', $input) ? trim((string)$input['lastName']) : null;
    if ($first !== null || $last !== null) {
      $full = trim(($first ?? '').' '.($last ?? ''));
      if ($full === '') $full = trim((string)($existing['name'] ?? ''));
      $fields['name'] = $full !== '' ? $full : null;
    }
    if (array_key_exists('kelas', $input)) {
      $kelas = trim((string)$input['kelas']);
      $fields['kelas'] = $kelas !== '' ? $kelas : null;
    }
    if (array_key_exists('bio', $input)) {
      $bio = trim((string)$input['bio']);
      $fields['bio'] = $bio !== '' ? $bio : null;
    }

    $existing = self::fetchUser($uid);
    $oldPhoto = $existing['avatar_path'] ?? null;

    if ($isMultipart && isset($_FILES['avatar']) && $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE) {
      $newPath = UploadService::saveAvatar($_FILES['avatar']);
      if ($newPath) {
        $fields['avatar_path'] = $newPath;
        $fields['avatar'] = $newPath;
        if ($oldPhoto && $oldPhoto !== $newPath) self::unlinkAvatar($oldPhoto);
      }
    }

    if ($fields) {
      global $pdo;
      $sets = [];
      $params = [':id'=>$uid];
      foreach ($fields as $k=>$v) {
        $sets[] = "$k = :$k";
        $params[":$k"] = $v;
      }
      $sql = 'UPDATE users SET '.implode(', ', $sets).', updated_at=NOW() WHERE id=:id';
      $pdo->prepare($sql)->execute($params);
    }

    json_ok(self::responsePayload($uid));
  }

  public static function deleteMe(): void {
    self::ensureColumns();
    $uid = require_auth();
    $user = self::fetchUser($uid);
    if (!empty($user['avatar_path'])) self::unlinkAvatar($user['avatar_path']);
    CascadeDelete::user($uid);
    session_destroy();
    json_ok(['message'=>'ACCOUNT_DELETED']);
  }
}
