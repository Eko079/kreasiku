<?php
declare(strict_types=1);

require_once __DIR__.'/../config.php';
require_once __DIR__.'/../utils/Response.php';
require_once __DIR__.'/../utils/Auth.php';
require_once __DIR__.'/../utils/Webhook.php';

class SettingsController {
  private static function assertOwner(int $designId, int $uid): array {
    global $pdo;
    $st = $pdo->prepare('SELECT * FROM designs WHERE id=?');
    $st->execute([$designId]);
    $row = $st->fetch();
    if (!$row) json_err('NOT_FOUND',404);
    if ((int)$row['owner_id'] !== $uid) json_err('FORBIDDEN',403);
    return $row;
  }

  public static function update(int $designId): void {
    $uid = require_auth();
    $row = self::assertOwner($designId, $uid);
    $in  = require_json();

    $vis  = $in['visibility']     ?? null; // public|private
    $cmt  = $in['commentEnabled'] ?? null; // bool -> allow_comments
    $dl   = $in['allowDownload']  ?? null; // bool
    $sch  = $in['scheduleAt']     ?? null; // string (optional)
    $now  = $in['publishNow']     ?? null; // bool

    $sets = [];
    $args = [];

    if ($vis !== null) {
      $v = strtolower((string)$vis);
      if (!in_array($v, ['public','private'], true)) json_err('INVALID_VISIBILITY',422);
      $sets[]='visibility=?'; $args[]=$v;
    }
    if ($cmt !== null) { $sets[]='allow_comments=?'; $args[] = $cmt ? 1 : 0; }
    if ($dl  !== null) { $sets[]='allow_download=?'; $args[] = $dl  ? 1 : 0; }

    $publishEvent = false;

    if ($sch !== null && trim((string)$sch) !== '') {
      $dt = strtotime((string)$sch);
      if ($dt === false) json_err('INVALID_SCHEDULE_AT',422);
      $iso = date('Y-m-d H:i:s', $dt);
      $sets[]='scheduled_at=?'; $args[]=$iso;

      if ($dt > time()) {
        $sets[]="status='scheduled'";
      } else {
        $sets[]="status='published'";
        $sets[]="published_at=NOW()";
        $publishEvent = true;
      }
    }

    if ($now === true) {
      $sets[]="status='published'";
      $sets[]="published_at=NOW()";
      $publishEvent = true;
    }

    if (empty($sets)) json_ok(['data'=>['updated'=>false]]);

    $sql = 'UPDATE designs SET '.implode(', ',$sets).', updated_at=NOW() WHERE id=?';
    $args[] = $designId;

    global $pdo;
    $pdo->beginTransaction();
    try{
      $pdo->prepare($sql)->execute($args);
      $pdo->commit();
    } catch (\Throwable $e) { $pdo->rollBack(); throw $e; }

    if ($publishEvent) {
      webhook_fire('design.published', [
        'designId' => (int)$designId,
        'ownerId'  => (int)$row['owner_id'],
        'title'    => $row['title'],
      ]);
    }

    json_ok(['data'=>['updated'=>true]]);
  }
}
