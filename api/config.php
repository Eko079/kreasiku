<?php
declare(strict_types=1);

$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
session_set_cookie_params([
  'lifetime' => 0,
  'path'     => '/',
  'secure'   => $isSecure,
  'httponly' => true,
  'samesite' => 'Lax',
]);
session_name('kreasiku_sid');
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('Asia/Jakarta');

/* ==== GANTI JIKA DIPERLUKAN SAAT DEPLOY ==== */
const DB_HOST = 'localhost';
const DB_NAME = 'kreasiku';
const DB_USER = 'admin';
const DB_PASS = '123456';

// const DB_HOST = 'localhost';
// const DB_NAME = 'kreasiku';
// const DB_USER = 'root';
// const DB_PASS = '';
/* ========================================== */

try {
  $pdo = new PDO(
    'mysql:host='.DB_HOST.';dbname='.DB_NAME.';charset=utf8mb4',
    DB_USER, DB_PASS,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );
} catch (Throwable $e) {
  http_response_code(500);
  header('Content-Type: application/json');
  echo json_encode(['ok'=>false,'error'=>'DB_CONNECT_FAILED','detail'=>$e->getMessage()]);
  exit;
}

/** Helper PDO (menghindari “Undefined function db()”) */
function db(): PDO { global $pdo; return $pdo; }

/** Base URL untuk membentuk URL file (download gambar) */
function base_url(): string {
  $root  = rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? ''), '/\\'); // .../api
  if (defined('APP_URL_ORIGIN') && APP_URL_ORIGIN !== '') {
    return rtrim(APP_URL_ORIGIN, '/') . $root;
  }
  $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
  return $proto.'://'.$host.$root;
}

/** Direktori upload (singkirkan duplikasi define) */
if (!defined('UPLOAD_DIR')) {
  define('UPLOAD_DIR', __DIR__ . '/../storage/uploads');
}
if (!is_dir(UPLOAD_DIR)) { @mkdir(UPLOAD_DIR, 0775, true); }

/** Optional */
if (!defined('APP_URL_ORIGIN')) define('APP_URL_ORIGIN', getenv('APP_URL_ORIGIN') ?: '');
if (!defined('SCHEDULER_KEY')) define('SCHEDULER_KEY', 'ganti_key_rahasia_anda');
if (!defined('WEBHOOK_URL'))  define('WEBHOOK_URL',  '');
if (!defined('GOOGLE_CLIENT_ID')) define('GOOGLE_CLIENT_ID', getenv('GOOGLE_CLIENT_ID') ?: '974372260444-803bbgcege5to6lbaoi412sbv511l2av.apps.googleusercontent.com');
if (!defined('MAGIC_SMTP_USER')) define('MAGIC_SMTP_USER', getenv('MAGIC_SMTP_USER') ?: 'akreasiku@gmail.com');
if (!defined('MAGIC_SMTP_PASS')) define('MAGIC_SMTP_PASS', getenv('MAGIC_SMTP_PASS') ?: 'khddzhauqeppuwfu');
if (!defined('CONTACT_EMAIL')) define('CONTACT_EMAIL', getenv('CONTACT_EMAIL') ?: MAGIC_SMTP_USER);
