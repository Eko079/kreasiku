<?php
declare(strict_types=1);
require_once __DIR__ . '/../utils/Response.php';

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }
$_SESSION = [];
@session_destroy();

json_ok(['message'=>'LOGGED_OUT']);
