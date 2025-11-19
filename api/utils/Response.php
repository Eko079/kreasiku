<?php
declare(strict_types=1);

function json_ok(array $data = [], int $code = 200): void {
  http_response_code($code);
  header('Content-Type: application/json');
  echo json_encode(['ok'=>true] + $data);
  exit;
}

function json_err(string $message, int $code = 400, array $extra = []): void {
  http_response_code($code);
  header('Content-Type: application/json');
  echo json_encode(['ok'=>false, 'error'=>$message] + $extra);
  exit;
}

function require_json(): array {
  $raw = file_get_contents('php://input') ?: '';
  $data = json_decode($raw, true);
  return is_array($data) ? $data : [];
}
