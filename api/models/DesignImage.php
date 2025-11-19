<?php
declare(strict_types=1);

require_once __DIR__.'/../config.php';

class DesignImage {
  public static function add(int $designId, string $filePath, int $position = 0, bool $isPrimary = false, ?string $mime = null): int {
    global $pdo;
    $st = $pdo->prepare('INSERT INTO design_images (design_id,file_path,is_primary,position,created_at,mime)
                         VALUES (?,?,?,?,NOW(),?)');
    $st->execute([$designId, $filePath, $isPrimary ? 1 : 0, $position, $mime]);
    return (int)$pdo->lastInsertId();
  }

  public static function listByDesign(int $designId): array {
    global $pdo;
    $st = $pdo->prepare('SELECT id,file_path,is_primary,position,mime FROM design_images
                         WHERE design_id=? ORDER BY position ASC, id ASC');
    $st->execute([$designId]);
    return $st->fetchAll();
  }
}
