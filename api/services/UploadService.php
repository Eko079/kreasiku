<?php
declare(strict_types=1);

require_once __DIR__.'/../config.php';

/**
 * Konvensi:
 * - File fisik disimpan di: UPLOAD_DIR . '/images'  (mis: .../api/storage/uploads/images)
 * - Nilai yang DIKEMBALIKAN: path relatif untuk DB: 'uploads/images/<nama>'
 */
class UploadService {
  public static function saveImages(array $files): array {
    $relPaths = [];
    $targetDir = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . 'images';
    if (!is_dir($targetDir)) @mkdir($targetDir, 0775, true);

    // Normalisasi struktur $_FILES menjadi array flat
    $items = [];
    if (isset($files['name']) && is_array($files['name'])) {
      for ($i=0; $i<count($files['name']); $i++) {
        $items[] = [
          'name'     => $files['name'][$i],
          'type'     => $files['type'][$i],
          'tmp_name' => $files['tmp_name'][$i],
          'error'    => $files['error'][$i],
          'size'     => $files['size'][$i],
        ];
      }
    } elseif (!empty($files['tmp_name'])) {
      $items[] = $files;
    }

    $max = min(3, count($items));
    for ($i=0; $i<$max; $i++) {
      $f = $items[$i];
      if (($f['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
      if (!isset($f['type']) || strpos($f['type'], 'image/') !== 0) continue;
      if (($f['size'] ?? 0) > 50 * 1024 * 1024) continue; // 50MB

      // validasi mime lebih kuat
      $fi = finfo_open(FILEINFO_MIME_TYPE);
      $mime = finfo_file($fi, $f['tmp_name']); finfo_close($fi);
      $allow = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp','image/gif'=>'gif'];
      if (!isset($allow[$mime])) continue;

      $name = 'img_'.date('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$allow[$mime];
      $dest = $targetDir . DIRECTORY_SEPARATOR . $name;

      if (!move_uploaded_file($f['tmp_name'], $dest)) continue;

      // simpan RELATIF ke /api/storage: `uploads/images/<file>`
      $relPaths[] = 'uploads/images/' . $name;
    }
    return $relPaths;
  }

  /**
   * Avatar — kembalikan URL publik (kolom `avatar_url` kamu memang URL).
   * Simpan file ke UPLOAD_DIR.'/avatars'
   */
  public static function saveAvatar(array $file): ?string {
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) return null;
    if (($file['size'] ?? 0) > 10 * 1024 * 1024) return null;
    if (!isset($file['type']) || strpos($file['type'], 'image/') !== 0) return null;

    $fi = finfo_open(FILEINFO_MIME_TYPE);
    $mime = finfo_file($fi, $file['tmp_name']); finfo_close($fi);
    $allow = ['image/png'=>'png','image/jpeg'=>'jpg','image/webp'=>'webp'];
    if (!isset($allow[$mime])) return null;

    $dir = rtrim(UPLOAD_DIR, '/\\') . DIRECTORY_SEPARATOR . 'avatars';
    if (!is_dir($dir)) @mkdir($dir, 0775, true);

    $name = 'avatar_'.bin2hex(random_bytes(6)).'.'.$allow[$mime];
    $dest = $dir . DIRECTORY_SEPARATOR . $name;
    if (!move_uploaded_file($file['tmp_name'], $dest)) return null;

    // bangun URL publik -> asumsikan base_url() mengarah ke root public
    // dan file tersaji di /api/storage/...
    $publicBase = rtrim(base_url(), '/').'/api/storage/avatars';
    return $publicBase.'/'.$name;
  }
}
