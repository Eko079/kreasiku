<?php
declare(strict_types=1);

require_once __DIR__.'/../utils/Response.php';
require_once __DIR__.'/../utils/RateLimit.php';
require_once __DIR__.'/../middleware/Auth.php';
require_once __DIR__.'/../models/Design.php';
require_once __DIR__.'/../config.php';

class ReactionController {
  /** POST /designs/:id/like  |  DELETE /designs/:id/like */
  public static function like(int $id): void {
    $uid = require_auth();
    ratelimit_check($uid, 'like', 30, 60);

    $row = Design::findById($id, true, $uid);
    if (!$row) json_err('NOT_FOUND',404);

    $method = $_SERVER['REQUEST_METHOD'];
    global $pdo;

    if ($method==='POST') {
      $pdo->beginTransaction();
      try{
        $pdo->prepare('INSERT IGNORE INTO likes (design_id,user_id,created_at) VALUES (?,?,NOW())')
            ->execute([$id,$uid]);
        $pdo->prepare('UPDATE designs SET likes_count=(SELECT COUNT(*) FROM likes WHERE design_id=?), updated_at=NOW() WHERE id=?')
            ->execute([$id,$id]);
        $pdo->commit();
      } catch(\Throwable $e){ $pdo->rollBack(); throw $e; }
      json_ok(['data'=>['liked'=>true]]);
    } else { // DELETE
      $pdo->beginTransaction();
      try{
        $pdo->prepare('DELETE FROM likes WHERE design_id=? AND user_id=?')->execute([$id,$uid]);
        $pdo->prepare('UPDATE designs SET likes_count=(SELECT COUNT(*) FROM likes WHERE design_id=?), updated_at=NOW() WHERE id=?')
            ->execute([$id,$id]);
        $pdo->commit();
      } catch(\Throwable $e){ $pdo->rollBack(); throw $e; }
      json_ok(['data'=>['liked'=>false]]);
    }
  }

  /** POST /designs/:id/save  |  DELETE /designs/:id/save */
  public static function save(int $id): void {
    $uid = require_auth();
    ratelimit_check($uid, 'save', 30, 60);

    $row = Design::findById($id, true, $uid);
    if (!$row) json_err('NOT_FOUND',404);

    $method = $_SERVER['REQUEST_METHOD'];
    global $pdo;

    if ($method==='POST') {
      $pdo->beginTransaction();
      try{
        $pdo->prepare('INSERT IGNORE INTO saves (design_id,user_id,created_at) VALUES (?,?,NOW())')
            ->execute([$id,$uid]);
        $pdo->prepare('UPDATE designs SET saves_count=(SELECT COUNT(*) FROM saves WHERE design_id=?), updated_at=NOW() WHERE id=?')
            ->execute([$id,$id]);
        $pdo->commit();
      } catch(\Throwable $e){ $pdo->rollBack(); throw $e; }
      json_ok(['data'=>['saved'=>true]]);
    } else {
      $pdo->beginTransaction();
      try{
        $pdo->prepare('DELETE FROM saves WHERE design_id=? AND user_id=?')->execute([$id,$uid]);
        $pdo->prepare('UPDATE designs SET saves_count=(SELECT COUNT(*) FROM saves WHERE design_id=?), updated_at=NOW() WHERE id=?')
            ->execute([$id,$id]);
        $pdo->commit();
      } catch(\Throwable $e){ $pdo->rollBack(); throw $e; }
      json_ok(['data'=>['saved'=>false]]);
    }
  }
}
