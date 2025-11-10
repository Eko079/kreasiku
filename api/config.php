<?php
declare(strict_types=1);

session_name('kreasiku_sid');
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }
date_default_timezone_set('Asia/Jakarta');

/* ==== GANTI JIKA DIPERLUKAN SAAT DEPLOY ==== */
const DB_HOST = '127.0.0.1';
const DB_NAME = 'kreasiku';
const DB_USER = 'root';
const DB_PASS = '';
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
  $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  $host  = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $root  = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/\\'); // .../api
  return $proto.'://'.$host.$root;
}

/** Direktori upload (singkirkan duplikasi define) */
if (!defined('UPLOAD_DIR')) {
  define('UPLOAD_DIR', __DIR__ . '/../storage/uploads');
}
if (!is_dir(UPLOAD_DIR)) { @mkdir(UPLOAD_DIR, 0775, true); }

/** Optional */
if (!defined('SCHEDULER_KEY')) define('SCHEDULER_KEY', 'ganti_key_rahasia_anda');
if (!defined('WEBHOOK_URL'))  define('WEBHOOK_URL',  '');
