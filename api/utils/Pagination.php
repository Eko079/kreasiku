<?php
declare(strict_types=1);

function page_params(): array {
  $page = max(1, (int)($_GET['page'] ?? 1));
  $per  = (int)($_GET['perPage'] ?? 12);
  if ($per < 1)  $per = 12;
  if ($per > 60) $per = 60;
  $offset = ($page - 1) * $per;
  return [$page, $per, $offset];
}

function add_pagination_meta(int $total, int $page, int $per): array {
  $pages = (int)ceil($total / max(1,$per));
  return ['total'=>$total,'page'=>$page,'perPage'=>$per,'pages'=>$pages];
}
