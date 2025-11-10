<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../middleware/Auth.php';

$uid = optional_auth();
if (!$uid) json_ok(['user'=>null]);

$st = db()->prepare('SELECT id,email,name,avatar,avatar_url,created_at FROM users WHERE id=:id');
$st->execute([':id'=>$uid]);
$user = $st->fetch();

json_ok(['user'=>$user]);
