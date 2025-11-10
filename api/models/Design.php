<?php
declare(strict_types=1);

require_once __DIR__.'/../config.php';

class Design {
  /**
   * Buat desain baru.
   * $payload:
   * - owner_id (int)        : required
   * - kind ('image'|'figma'): required
   * - title (string)        : optional -> default 'Untitled'
   * - description (string)  : optional
   * - category (enum)       : required ('portofolio','website','cv','desain','logo')
   * - visibility ('public'|'private') : optional default 'public'
   * - allow_comments (bool) : optional default true
   * - allow_download (bool) : optional default false (hanya untuk image)
   * - media_path (string|null) : isi jika kind='image'
   * - figma_url (string|null)  : isi jika kind='figma'
   * - status ('draft'|'scheduled'|'published') : optional default 'draft'
   * - scheduled_at (Y-m-d H:i:s|null)         : optional
   */
  public static function create(array $payload): int {
    global $pdo;

    $title   = trim((string)($payload['title'] ?? ''));
    if ($title === '') $title = 'Untitled';

    $category = strtolower((string)($payload['category'] ?? ''));
    $allowedCat = ['portofolio','website','cv','desain','logo'];
    if (!in_array($category, $allowedCat, true)) {
      throw new InvalidArgumentException('INVALID_CATEGORY');
    }

    $kind = (string)($payload['kind'] ?? '');
    if ($kind !== 'image' && $kind !== 'figma') {
      throw new InvalidArgumentException('INVALID_KIND');
    }

    $visibility     = ((string)($payload['visibility'] ?? 'public') === 'private') ? 'private' : 'public';
    $allowComments  = isset($payload['allow_comments']) ? (int)!!$payload['allow_comments'] : 1;
    $allowDownload  = isset($payload['allow_download']) ? (int)!!$payload['allow_download'] : 0;
    $status         = in_array(($payload['status'] ?? 'draft'), ['draft','scheduled','published'], true) ? $payload['status'] : 'draft';
    $scheduledAt    = $payload['scheduled_at'] ?? null;

    $mediaPath = $payload['media_path'] ?? null;
    $figmaUrl  = $payload['figma_url']  ?? null;

    if ($kind === 'image' && !$mediaPath) {
      throw new InvalidArgumentException('MEDIA_PATH_REQUIRED_FOR_IMAGE');
    }
    if ($kind === 'figma' && !$figmaUrl) {
      throw new InvalidArgumentException('FIGMA_URL_REQUIRED_FOR_FIGMA');
    }

    $st = $pdo->prepare(
      "INSERT INTO designs
        (owner_id, title, description, category, visibility, status,
         comment_enabled, allow_comments, allow_download,
         scheduled_at, published_at,
         comments_count, likes_count, saves_count,
         created_at, updated_at,
         kind, media_path, figma_url)
       VALUES
        (:owner_id, :title, :description, :category, :visibility, :status,
         :comment_enabled, :allow_comments, :allow_download,
         :scheduled_at, :published_at,
         0, 0, 0,
         NOW(), NOW(),
         :kind, :media_path, :figma_url)"
    );

    $publishedAt = ($status === 'published') ? date('Y-m-d H:i:s') : null;

    $st->execute([
      ':owner_id'         => (int)$payload['owner_id'],
      ':title'            => $title,
      ':description'      => ($payload['description'] ?? null),
      ':category'         => $category,
      ':visibility'       => $visibility,
      ':status'           => $status,
      // sinkronkan dua kolom ini agar kompatibel dengan kode lama/baru
      ':comment_enabled'  => $allowComments,
      ':allow_comments'   => $allowComments,
      ':allow_download'   => ($kind === 'image') ? $allowDownload : 0,
      ':scheduled_at'     => $scheduledAt,
      ':published_at'     => $publishedAt,
      ':kind'             => $kind,
      ':media_path'       => $mediaPath,
      ':figma_url'        => $figmaUrl,
    ]);

    return (int)$pdo->lastInsertId();
  }

  /** Ambil 1 desain; hormati visibilitas kecuali viewer adalah owner. */
  public static function findById(int $id, bool $includePrivateForViewer, int $viewerId = 0): ?array {
    global $pdo;

    $st = $pdo->prepare('SELECT * FROM designs WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    if (!$row) return null;

    if ($row['visibility'] !== 'public') {
      if (!($includePrivateForViewer && (int)$row['owner_id'] === (int)$viewerId)) {
        return null;
      }
    }
    return $row;
  }

  /**
   * List publik (status optional). Default: hanya `visibility='public'`.
   * $q: category?, owner_id?, sort?('newest'|'oldest'|'likes'|'saves'), status?('draft'|'scheduled'|'published')
   */
  public static function list(array $q): array {
    global $pdo;
    $w = ["visibility='public'"];
    $p = [];

    if (!empty($q['category'])) { $w[] = 'category = ?'; $p[] = strtolower($q['category']); }
    if (!empty($q['owner_id'])) { $w[] = 'owner_id = ?'; $p[] = (int)$q['owner_id']; }
    if (!empty($q['status']) && in_array($q['status'], ['draft','scheduled','published'], true)) {
      $w[] = 'status = ?'; $p[] = $q['status'];
    }

    $order = 'created_at DESC';
    if (!empty($q['sort'])) {
      if ($q['sort'] === 'likes')  $order = 'likes_count DESC, created_at DESC';
      if ($q['sort'] === 'saves')  $order = 'saves_count DESC, created_at DESC';
      if ($q['sort'] === 'oldest') $order = 'created_at ASC';
      if ($q['sort'] === 'newest') $order = 'created_at DESC';
    }

    $sql = 'SELECT id,owner_id,title,description,category,visibility,status,comment_enabled,allow_comments,allow_download,
                   scheduled_at,published_at,likes_count,saves_count,comments_count,kind,media_path,figma_url,created_at,updated_at
            FROM designs '.(count($w)?'WHERE '.implode(' AND ',$w):'').' ORDER BY '.$order.' LIMIT 60';
    $st = $pdo->prepare($sql);
    $st->execute($p);
    return $st->fetchAll();
  }
}
