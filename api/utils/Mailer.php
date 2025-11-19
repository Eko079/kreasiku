<?php
if (!defined('MAILER_LOADED')) {
  define('MAILER_LOADED', true);
}

function mailer_send(string $to, string $subject, string $body, string $fromName = 'Kreasiku'): bool {
  if (!defined('MAGIC_SMTP_USER') || !defined('MAGIC_SMTP_PASS')) {
    return false;
  }
  $username = MAGIC_SMTP_USER;
  $password = MAGIC_SMTP_PASS;
  if (!$username || !$password) return false;

  $conn = stream_socket_client('tcp://smtp.gmail.com:587', $errno, $errstr, 15);
  if (!$conn) return false;

  $read = function() use ($conn) {
    $data = '';
    while ($line = fgets($conn, 515)) {
      $data .= $line;
      if (isset($line[3]) && $line[3] === ' ') break;
    }
    return $data;
  };
  $write = function(string $cmd) use ($conn) {
    fwrite($conn, $cmd . "\r\n");
  };

  $read();
  $write('EHLO localhost'); $read();
  $write('STARTTLS');
  $resp = $read();
  if (strpos($resp, '220') !== 0) { fclose($conn); return false; }
  stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
  $write('EHLO localhost'); $read();
  $write('AUTH LOGIN'); $read();
  $write(base64_encode($username)); $read();
  $write(base64_encode($password));
  $resp = $read();
  if (strpos($resp, '235') !== 0) { fclose($conn); return false; }

  $write('MAIL FROM:<'.$username.'>'); $read();
  $write('RCPT TO:<'.$to.'>'); $read();
  $write('DATA'); $read();
  $headers = [
    'From: ' . $fromName . ' <'.$username.'>',
    'To: '.$to,
    'Subject: '.$subject,
    'MIME-Version: 1.0',
    'Content-Type: text/plain; charset=UTF-8',
  ];
  $message = implode("\r\n", $headers) . "\r\n\r\n" . $body . "\r\n.";
  $write($message); $read();
  $write('QUIT');
  fclose($conn);
  return true;
}
