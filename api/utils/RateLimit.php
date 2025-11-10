<?php
declare(strict_types=1);

require_once __DIR__.'/../config.php';
require_once __DIR__.'/Response.php';

/**
 * Rate limit sederhana berbasis DB:
 *  - key: kombinasi user_id + aksi (ex: "comment", "like", "save")
 *  - max N per window detik
 */
function ratelimit_check(int $userId, string $action, int $max, int $windowSeconds = 60): void {
  global $pdo;

  $pdo->prepare('DELETE FROM request_log WHERE created_at < (NOW() - INTERVAL :win SECOND)')
      ->execute([':win'=>$windowSeconds]);

  $st = $pdo->prepare('SELECT COUNT(*) FROM request_log WHERE user_id=? AND action=? AND created_at >= (NOW() - INTERVAL :win SECOND)');
  $st->execute([$userId, $action, $windowSeconds]);
  $count = (int)$st->fetchColumn();

  if ($count >= $max) {
    json_err('RATE_LIMIT', 429, ['message'=>"Terlalu sering. Coba lagi nanti."]);
  }

  $ins = $pdo->prepare('INSERT INTO request_log (user_id, action, created_at) VALUES (?,?,NOW())');
  $ins->execute([$userId, $action]);
}
