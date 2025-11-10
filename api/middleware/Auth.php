<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/Response.php';

function current_user_id(): ?int {
  if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
  return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function require_auth(): int {
  $uid = current_user_id();
  if (!$uid) json_err('UNAUTHENTICATED', 401);
  return $uid;
}

function optional_auth(): ?int {
  return current_user_id();
}
