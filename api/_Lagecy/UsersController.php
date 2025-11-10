<?php
declare(strict_types=1);

require_once __DIR__.'/../utils/Response.php';
require_once __DIR__.'/../middleware/Auth.php';
require_once __DIR__.'/../services/UploadService.php';
require_once __DIR__.'/../models/User.php';
require_once __DIR__.'/../config.php';

class UsersController {
  /** PATCH /users/me  (multipart utk avatar atau JSON utk name/class) */
  public static function updateMe(): void {
    $uid = require_auth();
    $isMultipart = (isset($_SERVER['CONTENT_TYPE']) && str_contains($_SERVER['CONTENT_TYPE'],'multipart/form-data'));

    global $pdo;
    $fields=[]; $params=[':id'=>$uid];

    if ($isMultipart && isset($_FILES['avatar'])) {
      $url = UploadService::saveAvatar($_FILES['avatar']);
      if ($url) { $fields[]='avatar_url=:avatar'; $params[':avatar']=$url; }
    } else {
      $in = require_json();
      if (isset($in['name']))  { $fields[]='name=:name';   $params[':name']=trim((string)$in['name']); }
      if (isset($in['class'])) { $fields[]='class=:class'; $params[':class']=trim((string)$in['class']); }
    }

    if (!$fields) json_ok(['message'=>'NO_CHANGE']);
    $sql='UPDATE users SET '.implode(', ',$fields).', updated_at=NOW() WHERE id=:id';
    $pdo->prepare($sql)->execute($params);

    $me = User::findById($uid);
    json_ok(['user'=>$me]);
  }

  /** DELETE /users/me — hapus akun + semua relasi */
  public static function deleteMe(): void {
    $uid = require_auth();
    require_once __DIR__.'/../services/CascadeDelete.php';
    CascadeDelete::user($uid);
    session_destroy();
    json_ok(['message'=>'ACCOUNT_DELETED']);
  }
}
