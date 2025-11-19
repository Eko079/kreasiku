<?php
declare(strict_types=1);

require_once __DIR__.'/../config.php';
require_once __DIR__.'/../utils/Response.php';
require_once __DIR__.'/../middleware/Auth.php';

/**
 * Urus cover & urutan gambar
 * - POST /designs/:id/images/reorder       { order: [imgId1,imgId2,...] }
 * - POST /designs/:id/images/:imgId/cover  (set cover)
 */
class ImageController {
  private static function assertOwner(int $designId, int $uid): void {
    global $pdo;
    $st = $pdo->prepare('SELECT owner_id FROM designs WHERE id=?');
    $st->execute([$designId]);
    $owner = $st->fetchColumn();
    if (!$owner) json_err('NOT_FOUND',404);
    if ((int)$owner !== $uid) json_err('FORBIDDEN',403);
  }

  public static function reorder(int $designId): void {
    $uid = require_auth();
    self::assertOwner($designId, $uid);

    $in = require_json();
    $order = $in['order'] ?? [];
    if (!is_array($order) || empty($order)) json_err('INVALID_ORDER',422);

    global $pdo;
    $pdo->beginTransaction();
    try {
      $pos = 1;
      $st = $pdo->prepare('UPDATE design_images SET position=? WHERE id=? AND design_id=?');
      foreach ($order as $imgId) {
        $imgId = (int)$imgId;
        if ($imgId <= 0) continue;
        $st->execute([$pos++, $imgId, $designId]);
      }
      $pdo->commit();
    } catch (\Throwable $e) {
      $pdo->rollBack(); throw $e;
    }

    json_ok(['data'=>['reordered'=>true]]);
  }

  public static function setCover(int $designId, int $imgId): void {
    $uid = require_auth();
    self::assertOwner($designId, $uid);

    global $pdo;
    $pdo->beginTransaction();
    try {
      $pdo->prepare('UPDATE design_images SET is_primary=0 WHERE design_id=?')->execute([$designId]);
      $pdo->prepare('UPDATE design_images SET is_primary=1 WHERE id=? AND design_id=?')->execute([$imgId, $designId]);
      $pdo->commit();
    } catch (\Throwable $e) {
      $pdo->rollBack(); throw $e;
    }

    json_ok(['data'=>['coverSet'=>true]]);
  }
}
