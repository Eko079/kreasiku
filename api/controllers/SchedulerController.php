<?php
declare(strict_types=1);

require_once __DIR__.'/../config.php';
require_once __DIR__.'/../utils/Response.php';
require_once __DIR__.'/../utils/Webhook.php';

/**
 * Jalankan via CRON/HTTP:
 *   GET /api/tasks/publish-due?key=YOUR_SCHEDULER_KEY
 * Mempublish desain yang status=scheduled dan scheduled_at <= NOW()
 */
class SchedulerController {
  public static function publishDue(): void {
    if (!defined('SCHEDULER_KEY') || SCHEDULER_KEY === '') {
      json_err('SCHEDULER_KEY_NOT_SET', 500);
    }
    $key = (string)($_GET['key'] ?? '');
    if ($key !== SCHEDULER_KEY) json_err('UNAUTHORIZED', 401);

    global $pdo;

    // Ambil daftar yang akan dipublish (untuk webhook per item)
    $sel = $pdo->query("SELECT id, owner_id, title FROM designs
      WHERE status='scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= NOW()");
    $rows = $sel->fetchAll();

    // Publish massal
    $upd = $pdo->exec("UPDATE designs
      SET status='published', published_at=NOW(), updated_at=NOW()
      WHERE status='scheduled' AND scheduled_at IS NOT NULL AND scheduled_at <= NOW()");

    // Webhook
    foreach ($rows as $r) {
      webhook_fire('design.published', [
        'designId' => (int)$r['id'],
        'ownerId'  => (int)$r['owner_id'],
        'title'    => $r['title'],
      ]);
    }

    json_ok(['data'=>['publishedCount'=>(int)$upd]]);
  }
}
