<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../utils/Response.php';
require_once __DIR__ . '/../utils/Auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') json_err('METHOD_NOT_ALLOWED', 405);

$uid = require_auth();
$id  = (int)($_POST['id'] ?? 0);
if ($id<=0) json_err('MISSING_ID', 422);

$db = db();
$st = $db->prepare("SELECT owner_id FROM designs WHERE id=:id");
$st->execute([':id'=>$id]);
$own = $st->fetchColumn();
if (!$own) json_err('NOT_FOUND',404);
if ((int)$own !== $uid) json_err('FORBIDDEN',403);

/* fields opsional */
$fields = [];
$bind   = [':id'=>$id];

if (isset($_POST['title']))         { $fields[]="title=:title";               $bind[':title']=trim((string)$_POST['title']) ?: null; }
if (isset($_POST['description']))   { $fields[]="description=:description";   $bind[':description']=trim((string)$_POST['description']) ?: null; }
if (isset($_POST['visibility']))    { $v=strtolower((string)$_POST['visibility']); $fields[]="visibility=:visibility"; $bind[':visibility']= ($v==='private'?'private':'public'); }
if (isset($_POST['allow_comments'])){ $fields[]="allow_comments=:allow_comments"; $bind[':allow_comments']= (int)!!$_POST['allow_comments']; }
if (isset($_POST['allow_download'])){ $fields[]="allow_download=:allow_download"; $bind[':allow_download']= (int)!!$_POST['allow_download']; }

if (!$fields) json_err('NOTHING_TO_UPDATE',422);

$sql = "UPDATE designs SET ".implode(', ',$fields)." WHERE id=:id";
$db->prepare($sql)->execute($bind);

json_ok(['updated'=>true]);
