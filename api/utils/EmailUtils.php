<?php
function is_disposable_email(string $email): bool {
  $domain = strtolower(substr(strrchr($email, '@') ?: '', 1));
  if ($domain === '') return true;
  $blocked = [
    'mailinator.com','tempmail.com','10minutemail.com','guerrillamail.com','trashmail.com','yopmail.com',
    'getnada.com','emailondeck.com','maildrop.cc','mytemp.email','tempmailo.com','fexmail.com','fakeinbox.com',
    'anonaddy.com','sharklasers.com','dispostable.com'
  ];
  return in_array($domain, $blocked, true);
}
