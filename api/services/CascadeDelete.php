<?php
declare(strict_types=1);

require_once __DIR__.'/../config.php';
require_once __DIR__.'/StorageBin.php';

class CascadeDelete {
  private static array $tableCache = [];

  private static function tableExists(PDO $pdo, string $table): bool {
    if (array_key_exists($table, self::$tableCache)) {
      return self::$tableCache[$table];
    }
    $st = $pdo->prepare('SHOW TABLES LIKE ?');
    $st->execute([$table]);
    return self::$tableCache[$table] = (bool)$st->fetchColumn();
  }

  private static function deleteIfExists(PDO $pdo, string $table, string $column, $value): void {
    if (!self::tableExists($pdo, $table)) return;
    $pdo->prepare("DELETE FROM `{$table}` WHERE {$column}=?")->execute([$value]);
  }

  public static function user(int $userId): void {
    global $pdo;
    $pdo->beginTransaction();
    try {
      foreach (['likes','saves','design_likes','design_saves'] as $tbl) {
        self::deleteIfExists($pdo, $tbl, 'user_id', $userId);
      }

      self::deleteIfExists($pdo, 'comments', 'user_id', $userId);

      $designIds = [];
      if (self::tableExists($pdo, 'designs')) {
        $st = $pdo->prepare('SELECT id, media_path FROM designs WHERE owner_id=?');
        $st->execute([$userId]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        foreach ($rows as $row) {
          $designIds[] = (int)$row['id'];
          if (!empty($row['media_path'])) {
            StorageBin::moveToBin($row['media_path']);
          }
        }
      }

      if ($designIds && self::tableExists($pdo, 'design_images')) {
        $placeholders = implode(',', array_fill(0, count($designIds), '?'));
        $st = $pdo->prepare("SELECT file_path FROM design_images WHERE design_id IN ($placeholders)");
        $st->execute($designIds);
        while ($file = $st->fetchColumn()) {
          StorageBin::moveToBin($file);
        }
        $pdo->prepare("DELETE FROM design_images WHERE design_id IN ($placeholders)")->execute($designIds);
      }

      if (self::tableExists($pdo, 'designs')) {
        $pdo->prepare('DELETE FROM designs WHERE owner_id=?')->execute([$userId]);
      }

      if (self::tableExists($pdo, 'users')) {
        $st = $pdo->prepare('SELECT avatar_path FROM users WHERE id=?');
        $st->execute([$userId]);
        $avatar = $st->fetchColumn();
        if ($avatar) {
          StorageBin::moveToBin($avatar);
        }
        $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$userId]);
      }

      if (self::tableExists($pdo, 'designs')) {
        $pdo->exec("
          UPDATE designs d
          SET
            likes_count = (SELECT COUNT(*) FROM design_likes dl WHERE dl.design_id=d.id),
            saves_count = (SELECT COUNT(*) FROM design_saves ds WHERE ds.design_id=d.id),
            comments_count = (SELECT COUNT(*) FROM comments c WHERE c.design_id=d.id),
            updated_at = NOW()
        ");
      }

      $pdo->commit();
    } catch (\Throwable $e) {
      $pdo->rollBack(); throw $e;
    }
  }
}
