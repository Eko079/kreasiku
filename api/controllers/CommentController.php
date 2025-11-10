<?php
declare(strict_types=1);

require_once __DIR__.'/../utils/Response.php';
require_once __DIR__.'/../utils/Pagination.php';
require_once __DIR__.'/../utils/RateLimit.php';
require_once __DIR__.'/../utils/Sanitize.php';
require_once __DIR__.'/../utils/Webhook.php';
require_once __DIR__.'/../utils/Auth.php'; // pastikan ini, bukan middleware/Auth.php
require_once __DIR__.'/../config.php';

class CommentController {
  public static function index(int $id): void {
    global $pdo;
    $uid = optional_auth();

    $d = $pdo->prepare('SELECT owner_id,status,visibility,allow_comments FROM designs WHERE id=?');
    $d->execute([$id]);
    $des = $d->fetch();
    if (!$des) json_err('NOT_FOUND',404);

    $isOwner   = $uid && (int)$des['owner_id']===(int)$uid;
    $isVisible = ($des['status']==='published' && $des['visibility']==='public') || $isOwner;
    if (!$isVisible) json_err('FORBIDDEN',403);

    [$page,$per,$offset] = page_params();
    $ct = $pdo->prepare('SELECT COUNT(*) FROM comments WHERE design_id=?');
    $ct->execute([$id]);
    $total = (int)$ct->fetchColumn();

    $sql = 'SELECT c.id, c.user_id, u.name AS user_name, u.avatar_url, c.body, c.created_at
            FROM comments c
            JOIN users u ON u.id = c.user_id
            WHERE c.design_id = ?
            ORDER BY c.id DESC
            LIMIT ? OFFSET ?';
    $st  = $pdo->prepare($sql);
    $st->execute([$id, $per, $offset]);
    $rows = $st->fetchAll();

    json_ok(['data'=>$rows,'meta'=>add_pagination_meta($total,$page,$per)]);
  }

  public static function create(int $id): void {
    $uid = require_auth();
    ratelimit_check($uid,'comment',10,60);

    global $pdo;
    $d = $pdo->prepare('SELECT owner_id,status,visibility,allow_comments FROM designs WHERE id=?');
    $d->execute([$id]);
    $des = $d->fetch();
    if (!$des) json_err('NOT_FOUND',404);

    $isOwner = (int)$des['owner_id']===$uid;
    if (!($des['status']==='published' && $des['visibility']==='public') && !$isOwner) {
      json_err('FORBIDDEN',403);
    }
    if ((int)$des['allow_comments']!==1) json_err('COMMENT_DISABLED',403);

    $in   = require_json();
    $body = sanitize_text((string)($in['body'] ?? ''));
    if ($body==='') json_err('EMPTY_BODY',422);

    $pdo->beginTransaction();
    try{
      $pdo->prepare('INSERT INTO comments (design_id,user_id,body,created_at) VALUES (?,?,?,NOW())')
          ->execute([$id,$uid,$body]);
      $pdo->prepare('UPDATE designs SET comments_count=(SELECT COUNT(*) FROM comments WHERE design_id=?), updated_at=NOW() WHERE id=?')
          ->execute([$id,$id]);
      $cid = (int)$pdo->lastInsertId();
      $sel = $pdo->prepare('SELECT c.id, c.user_id, u.name AS user_name, u.avatar_url, c.body, c.created_at
                            FROM comments c JOIN users u ON u.id=c.user_id WHERE c.id=?');
      $sel->execute([$cid]);
      $data = $sel->fetch();
      $pdo->commit();
    } catch(\Throwable $e){ $pdo->rollBack(); throw $e; }

    webhook_fire('comment.created', [
      'designId' => (int)$id,
      'commentId'=> (int)$data['id'],
      'userId'   => (int)$data['user_id'],
    ]);

    json_ok(['data'=>$data], 201);
  }

  public static function destroy(int $cid): void {
    $uid = require_auth();
    global $pdo;

    $st = $pdo->prepare('SELECT c.design_id, c.user_id, d.owner_id
                         FROM comments c JOIN designs d ON d.id=c.design_id WHERE c.id=?');
    $st->execute([$cid]);
    $row = $st->fetch();
    if (!$row) json_err('NOT_FOUND',404);

    $canDelete = ((int)$row['user_id']===$uid) || ((int)$row['owner_id']===$uid);
    if (!$canDelete) json_err('FORBIDDEN',403);

    $desId = (int)$row['design_id'];

    $pdo->beginTransaction();
    try{
      $pdo->prepare('DELETE FROM comments WHERE id=?')->execute([$cid]);
      $pdo->prepare('UPDATE designs SET comments_count=(SELECT COUNT(*) FROM comments WHERE design_id=?), updated_at=NOW() WHERE id=?')
          ->execute([$desId,$desId]);
      $pdo->commit();
    } catch(\Throwable $e){ $pdo->rollBack(); throw $e; }

    json_ok(['data'=>['deleted'=>true]]);
  }
}
