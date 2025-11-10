<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/Response.php';

$key = $_GET['key'] ?? '';
if ($key !== SCHEDULER_KEY) json_err('UNAUTHORIZED', 401);

try {
      $st = db()->prepare("UPDATE designs
        SET status='published', published_at=NOW(), updated_at=NOW()
        WHERE status='scheduled'
          AND scheduled_at IS NOT NULL
          AND scheduled_at <= NOW()");

  $st->execute();
  json_ok(['published'=>$st->rowCount()]);
} catch (Throwable $e) {
  json_err('DB_ERROR: '.$e->getMessage(), 500);
}
