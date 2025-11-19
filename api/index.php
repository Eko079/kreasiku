<?php
declare(strict_types=1);

require_once __DIR__.'/config.php';
require_once __DIR__.'/utils/Response.php';
require_once __DIR__.'/routes.php';

/** CORS (kalau perlu lintas origin — sesuaikan domain kamu) */
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Credentials: true');
header('Access-Control-Allow-Headers: Content-Type, Authorization');
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(204); exit;
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

$route = resolve_route($method, $uri);
if ($route === null) {
  json_err('NOT_FOUND', 404);
}

try {
  if (is_array($route)) {
    $call = $route[0];
    $param = $route[1] ?? null;
    if ($param !== null) { $call($param); }
    else { $call(); }
  } else {
    $route(); // callable langsung
  }
} catch (Throwable $e) {
  json_err('SERVER_ERROR', 500, ['detail'=>$e->getMessage()]);
}
