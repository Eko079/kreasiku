<?php
declare(strict_types=1);

/**
 * Sanitasi teks komentar:
 * - Potong panjang maksimal
 * - Hapus tag berbahaya, izinkan subset kecil
 * - Blacklist kata kasar (sederhana)
 */
function sanitize_text(string $text, int $maxLen = 2000): string {
  $text = trim($text);
  if ($text === '') return '';

  // Batasi panjang
  if (mb_strlen($text) > $maxLen) {
    $text = mb_substr($text, 0, $maxLen);
  }

  // Strip tags berbahaya; izinkan <b><i><strong><em><code><a>
  $text = strip_tags($text, '<b><i><strong><em><code><a>');

  // Normalisasi spasi
  $text = preg_replace('/\s{2,}/u', ' ', $text) ?? $text;

  // Blacklist sederhana
  $blacklist = [
    'bangsat','anjing','tolol','goblok','kontol','memek','sarap','idiot'
  ];
  $textLower = mb_strtolower($text);
  foreach ($blacklist as $bad) {
    if (mb_strpos($textLower, $bad) !== false) {
      $text = preg_replace('/'.$bad.'/iu', str_repeat('*', mb_strlen($bad)), $text);
    }
  }

  // Bersihkan atribut href berbahaya pada <a>
  $text = preg_replace_callback('/<a\b[^>]*>/i', function($m){
    $tag = $m[0];
    // buang javascript: dan data:
    $tag = preg_replace('/href\s*=\s*([\'"])\s*(javascript:|data:)[^\'"]*\1/i', 'href="#"', $tag);
    return $tag;
  }, $text) ?? $text;

  return $text;
}
