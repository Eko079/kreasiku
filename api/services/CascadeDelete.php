<?php
declare(strict_types=1);

require_once __DIR__.'/../config.php';

class CascadeDelete {
  public static function user(int $userId): void {
    global $pdo;
    $pdo->beginTransaction();
    try {
      // 1) Hapus interaksi milik user di SEMUA varian tabel
      $pdo->prepare('DELETE FROM likes WHERE user_id=?')->execute([$userId]);
      $pdo->prepare('DELETE FROM saves WHERE user_id=?')->execute([$userId]);
      $pdo->prepare('DELETE FROM design_likes WHERE user_id=?')->execute([$userId]);
      $pdo->prepare('DELETE FROM design_saves WHERE user_id=?')->execute([$userId]);

      // 2) Hapus komentar milik user
      $pdo->prepare('DELETE FROM comments WHERE user_id=?')->execute([$userId]);

      // 3) Hapus semua desain milik user (FK akan menghapus design_images & comments terkait)
      $pdo->prepare('DELETE FROM designs WHERE owner_id=?')->execute([$userId]);

      // 4) Hapus user
      $pdo->prepare('DELETE FROM users WHERE id=?')->execute([$userId]);

      // 5) Rebuild counter agar tidak stale (global, aman & sederhana)
      $pdo->exec("
        UPDATE designs d
        SET
          likes_count = (SELECT COUNT(*) FROM design_likes dl WHERE dl.design_id=d.id),
          saves_count = (SELECT COUNT(*) FROM design_saves ds WHERE ds.design_id=d.id),
          comments_count = (SELECT COUNT(*) FROM comments c WHERE c.design_id=d.id),
          updated_at = NOW()
      ");

      $pdo->commit();
    } catch (\Throwable $e) {
      $pdo->rollBack(); throw $e;
    }
  }
}
