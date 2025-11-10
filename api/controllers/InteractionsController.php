<?php
declare(strict_types=1);

require_once __DIR__.'/../utils/Response.php';
require_once __DIR__.'/../utils/Auth.php';
require_once __DIR__.'/../config.php';

class InteractionsController {
  public static function like(int $designId): void {
    $uid = require_auth();
    global $pdo;
    $pdo->beginTransaction();
    try {
      $pdo->prepare('INSERT IGNORE INTO design_likes(design_id,user_id,created_at) VALUES(?,?,NOW())')
          ->execute([$designId,$uid]);
      $pdo->prepare('UPDATE designs SET likes_count = (SELECT COUNT(*) FROM design_likes WHERE design_id=?), updated_at=NOW() WHERE id=?')
          ->execute([$designId,$designId]);
      $pdo->commit();
      json_ok(['liked'=>true]);
    } catch (\Throwable $e){ $pdo->rollBack(); json_err('LIKE_FAIL',500); }
  }

  public static function unlike(int $designId): void {
    $uid = require_auth();
    global $pdo;
    $pdo->beginTransaction();
    try {
      $pdo->prepare('DELETE FROM design_likes WHERE design_id=? AND user_id=?')->execute([$designId,$uid]);
      $pdo->prepare('UPDATE designs SET likes_count = (SELECT COUNT(*) FROM design_likes WHERE design_id=?), updated_at=NOW() WHERE id=?')
          ->execute([$designId,$designId]);
      $pdo->commit();
      json_ok(['liked'=>false]);
    } catch (\Throwable $e){ $pdo->rollBack(); json_err('UNLIKE_FAIL',500); }
  }

  public static function save(int $designId): void {
    $uid = require_auth();
    global $pdo;
    $pdo->beginTransaction();
    try {
      $pdo->prepare('INSERT IGNORE INTO design_saves(design_id,user_id,created_at) VALUES(?,?,NOW())')
          ->execute([$designId,$uid]);
      $pdo->prepare('UPDATE designs SET saves_count = (SELECT COUNT(*) FROM design_saves WHERE design_id=?), updated_at=NOW() WHERE id=?')
          ->execute([$designId,$designId]);
      $pdo->commit();
      json_ok(['saved'=>true]);
    } catch (\Throwable $e){ $pdo->rollBack(); json_err('SAVE_FAIL',500); }
  }

  public static function unsave(int $designId): void {
    $uid = require_auth();
    global $pdo;
    $pdo->beginTransaction();
    try {
      $pdo->prepare('DELETE FROM design_saves WHERE design_id=? AND user_id=?')->execute([$designId,$uid]);
      $pdo->prepare('UPDATE designs SET saves_count = (SELECT COUNT(*) FROM design_saves WHERE design_id=?), updated_at=NOW() WHERE id=?')
          ->execute([$designId,$designId]);
      $pdo->commit();
      json_ok(['saved'=>false]);
    } catch (\Throwable $e){ $pdo->rollBack(); json_err('UNSAVE_FAIL',500); }
  }
}
