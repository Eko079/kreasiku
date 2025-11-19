<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/utils/Response.php';
require_once __DIR__ . '/utils/Mailer.php';
require_once __DIR__ . '/utils/EmailUtils.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  json_err('METHOD_NOT_ALLOWED', 405);
}

$payload = json_decode(file_get_contents('php://input') ?: '[]', true);
$name = trim((string)($payload['name'] ?? ''));
$email = strtolower(trim((string)($payload['email'] ?? '')));
$message = trim((string)($payload['message'] ?? ''));

if ($name === '') json_err('NAME_REQUIRED', 422);
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) json_err('INVALID_EMAIL', 422);
if (is_disposable_email($email)) json_err('DISPOSABLE_EMAIL_BLOCKED', 422);
if ($message === '') json_err('MESSAGE_REQUIRED', 422);

ensure_contact_table();

$db = db();
$st = $db->prepare('INSERT INTO contact_messages (name,email,message,created_at) VALUES (:n,:e,:m,NOW())');
$st->execute([':n'=>$name, ':e'=>$email, ':m'=>$message]);

$body = "Nama: $name\nEmail: $email\n\nPesan:\n$message\n";
$to = defined('CONTACT_EMAIL') && CONTACT_EMAIL ? CONTACT_EMAIL : MAGIC_SMTP_USER;
if ($to) {
  mailer_send($to, 'Pesan baru dari Kreasiku', $body);
}

json_ok(['message'=>'CONTACT_RECEIVED']);

function ensure_contact_table(): void {
  $db = db();
  $db->exec('CREATE TABLE IF NOT EXISTS contact_messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(190) NOT NULL,
    email VARCHAR(190) NOT NULL,
    message TEXT NOT NULL,
    created_at DATETIME NOT NULL,
    INDEX email_idx (email)
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4');
}
