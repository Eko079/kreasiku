<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
header('Content-Type: application/json; charset=utf-8');

$out = ['ok' => true, 'db' => 'unknown', 'error' => null];

try {
  $pdo = db();
  $pdo->query('SELECT 1');
  $out['db'] = 'connected';
} catch (Throwable $e) {
  $out['db'] = 'error';
  $out['error'] = $e->getMessage();
}

echo json_encode($out);
