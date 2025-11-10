<?php
declare(strict_types=1);

/**
 * Kirim webhook (opsional). Set WEBHOOK_URL di config/env jika ingin aktif.
 * $event contoh: "design.published", "comment.created"
 */
function webhook_fire(string $event, array $payload): void {
  $url = defined('WEBHOOK_URL') ? WEBHOOK_URL : (getenv('WEBHOOK_URL') ?: '');
  if (!$url) return;

  $body = json_encode([
    'event'   => $event,
    'payload' => $payload,
    'sentAt'  => gmdate('c'),
  ], JSON_UNESCAPED_UNICODE);

  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 5,
  ]);
  curl_exec($ch);
  curl_close($ch);
}
