<?php
declare(strict_types=1);

require_once __DIR__.'/../config.php';

class Comment {
  public static function countByDesign(int $designId): int {
    global $pdo;
    $st = $pdo->prepare('SELECT COUNT(*) FROM comments WHERE design_id=?');
    $st->execute([$designId]);
    return (int)$st->fetchColumn();
  }

  public static function listByDesign(int $designId, int $limit, int $offset): array {
    global $pdo;
    $sql = 'SELECT c.id, c.user_id, u.name AS user_name, u.avatar_url, c.body, c.created_at
            FROM comments c
            JOIN users u ON u.id = c.user_id
            WHERE c.design_id = ?
            ORDER BY c.id DESC
            LIMIT ? OFFSET ?';
    $st = $pdo->prepare($sql);
    $st->execute([$designId, $limit, $offset]);
    return $st->fetchAll();
  }
}
