<?php
declare(strict_types=1);

require_once __DIR__.'/../config.php';

class StorageBin {
  private static ?string $root = null;

  private static function storageRoot(): string {
    if (self::$root !== null) return self::$root;
    return self::$root = rtrim(dirname(UPLOAD_DIR), '/\\');
  }

  private static function normalize(?string $path): ?string {
    if (!$path) return null;
    $clean = ltrim(str_replace('\\','/', (string)$path), '/');
    if ($clean === '') return null;
    if (str_starts_with($clean, 'storage/')) {
      $clean = ltrim(substr($clean, strlen('storage/')), '/');
    }
    return $clean ?: null;
  }

  public static function moveToBin(?string $path): void {
    $clean = self::normalize($path);
    if (!$clean) return;

    $src = self::storageRoot() . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $clean);
    if (!is_file($src)) return;

    $destBase = self::storageRoot() . DIRECTORY_SEPARATOR . 'bin';
    $dest = $destBase . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $clean);
    $destDir = dirname($dest);
    if (!is_dir($destDir)) @mkdir($destDir, 0775, true);

    if (is_file($dest)) {
      $info = pathinfo($dest);
      $suffix = date('YmdHis') . '_' . bin2hex(random_bytes(3));
      $name = ($info['filename'] ?? 'file') . '_' . $suffix;
      $ext  = isset($info['extension']) ? '.'.$info['extension'] : '';
      $dest = $destDir . DIRECTORY_SEPARATOR . $name . $ext;
    }

    @rename($src, $dest);
  }
}
