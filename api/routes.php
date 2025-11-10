<?php
declare(strict_types=1);

require_once __DIR__.'/controllers/AuthController.php';
require_once __DIR__.'/controllers/DesignsController.php';
require_once __DIR__.'/controllers/InteractionsController.php';
require_once __DIR__.'/controllers/CommentsController.php';
require_once __DIR__.'/controllers/UsersController.php';
require_once __DIR__.'/controllers/SearchController.php';
require_once __DIR__.'/controllers/MeController.php';
require_once __DIR__.'/controllers/SettingsController.php';
require_once __DIR__.'/controllers/FeedController.php';
require_once __DIR__.'/controllers/DesignDetailController.php';
require_once __DIR__.'/controllers/ReactionController.php';
require_once __DIR__.'/controllers/CommentController.php';
require_once __DIR__.'/controllers/SchedulerController.php';
require_once __DIR__.'/controllers/ImageController.php';

function starts_with($h,$n){ return strncmp($h,$n,strlen($n))===0; }

/** Return callable atau [callable, param...] */
function resolve_route(string $method, string $path) {
  if (starts_with($path, '/api')) $path = substr($path, 4);
  if ($path === '') $path = '/';

  // Auth
  if ($method==='POST' && $path==='/auth/register') return ['AuthController::register'];
  if ($method==='POST' && $path==='/auth/login')    return ['AuthController::login'];
  if ($method==='GET'  && $path==='/auth/me')       return ['AuthController::me'];
  if ($method==='POST' && $path==='/auth/logout')   return ['AuthController::logout'];

  // Users (profile)
  if ($method==='PATCH' && $path==='/users/me')     return ['UsersController::updateMe'];
  if ($method==='DELETE'&& $path==='/users/me')     return ['UsersController::deleteMe'];

  // Designs
  if ($method==='GET'  && $path==='/designs')       return ['DesignsController::index'];
  if ($method==='POST' && $path==='/designs')       return ['DesignsController::create'];
  if ($method==='GET'  && preg_match('#^/designs/(\d+)$#',$path,$m)) return ['DesignsController::show',(int)$m[1]];
  if ($method==='PATCH'&& preg_match('#^/designs/(\d+)$#',$path,$m)) return ['DesignsController::update',(int)$m[1]];
  if ($method==='DELETE'&&preg_match('#^/designs/(\d+)$#',$path,$m)) return ['DesignsController::destroy',(int)$m[1]];
  if ($method==='GET'  && preg_match('#^/designs/(\d+)/download$#',$path,$m)) return ['DesignsController::download',(int)$m[1]];

  // Interactions
  if ($method==='POST'   && preg_match('#^/designs/(\d+)/like$#',$path,$m)) return ['InteractionsController::like',(int)$m[1]];
  if ($method==='DELETE' && preg_match('#^/designs/(\d+)/like$#',$path,$m)) return ['InteractionsController::unlike',(int)$m[1]];
  if ($method==='POST'   && preg_match('#^/designs/(\d+)/save$#',$path,$m)) return ['InteractionsController::save',(int)$m[1]];
  if ($method==='DELETE' && preg_match('#^/designs/(\d+)/save$#',$path,$m)) return ['InteractionsController::unsave',(int)$m[1]];

  // Comments
  if ($method==='GET'  && preg_match('#^/designs/(\d+)/comments$#',$path,$m)) return ['CommentsController::index',(int)$m[1]];
  if ($method==='POST' && preg_match('#^/designs/(\d+)/comments$#',$path,$m)) return ['CommentsController::create',(int)$m[1]];
  if ($method==='DELETE'&&preg_match('#^/comments/(\d+)$#',$path,$m))        return ['CommentsController::destroy',(int)$m[1]];

  // Search
  if ($method==='GET' && $path==='/search') return ['SearchController::query'];

  // Me
  if ($method==='GET' && $path==='/me/designs') return ['MeController::myDesigns'];
  if ($method==='GET' && $path==='/me/saved')   return ['MeController::saved'];

  // Settings (quick toggles)
  if ($method==='PATCH' && preg_match('#^/designs/(\d+)/settings/comments$#',$path,$m))   return ['SettingsController::comments',(int)$m[1]];
  if ($method==='PATCH' && preg_match('#^/designs/(\d+)/settings/visibility$#',$path,$m)) return ['SettingsController::visibility',(int)$m[1]];
  if ($method==='PATCH' && preg_match('#^/designs/(\d+)/settings/download$#',$path,$m))   return ['SettingsController::download',(int)$m[1]];
  if ($method==='PATCH' && preg_match('#^/designs/(\d+)/settings/schedule$#',$path,$m))   return ['SettingsController::schedule',(int)$m[1]];

  // Feed
  if ($method==='GET' && $path==='/feed/trending') return ['FeedController::trending'];

  // Detail & download
  if ($method==='GET' && preg_match('#^/designs/(\d+)$#',$path,$m))            return ['DesignDetailController::show',(int)$m[1]];
  if ($method==='GET' && preg_match('#^/designs/(\d+)/download$#',$path,$m))    return ['DesignDetailController::download',(int)$m[1]];

  // Reactions
  if (preg_match('#^/designs/(\d+)/(like|save)$#',$path,$m)) {
    $id=(int)$m[1]; $act=$m[2];
    if ($act==='like' && in_array($method,['POST','DELETE'],true)) return ['ReactionController::like',$id];
    if ($act==='save' && in_array($method,['POST','DELETE'],true)) return ['ReactionController::save',$id];
  }

  // Comments
  if ($method==='GET'  && preg_match('#^/designs/(\d+)/comments$#',$path,$m)) return ['CommentController::index',(int)$m[1]];
  if ($method==='POST' && preg_match('#^/designs/(\d+)/comments$#',$path,$m)) return ['CommentController::create',(int)$m[1]];
  if ($method==='DELETE'&&preg_match('#^/comments/(\d+)$#',$path,$m))         return ['CommentController::destroy',(int)$m[1]];

  // Search
  if ($method==='GET' && $path==='/search') return ['SearchController::query'];

  // Scheduler
  if ($method==='GET' && $path==='/tasks/publish-due') return ['SchedulerController::publishDue'];

  // Images: reorder & cover
  if ($method==='POST' && preg_match('#^/designs/(\d+)/images/reorder$#',$path,$m)) return ['ImageController::reorder', (int)$m[1]];
  if ($method==='POST' && preg_match('#^/designs/(\d+)/images/(\d+)/cover$#',$path,$m)) return ['ImageController::setCover', (int)$m[1], (int)$m[2]];

  // Settings
  if ($method==='PUT' && preg_match('#^/designs/(\d+)/settings$#',$path,$m)) return ['SettingsController::update', (int)$m[1]];

  return null;
}
