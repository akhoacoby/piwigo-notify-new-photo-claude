<?php
/*
Plugin Name: Notify New Photos
Version: 2.0.0
Plugin URI: auto
Author: akhoacoby
Author URI: auto
Description: Emails the configured users and groups when new photos are added, respecting each recipient's album permissions and language. Rich HTML email with thumbnails (plain-text fallback). Configurable interval; force a run from the settings page or the web-service method notify_new_photos.run.
Has Settings: true
*/

if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

// +-----------------------------------------------------------------------+
// | Folder-name guard                                                     |
// +-----------------------------------------------------------------------+
if (basename(dirname(__FILE__)) != 'notify_new_photos')
{
  add_event_handler('init', 'notify_new_photos_folder_error');
  function notify_new_photos_folder_error()
  {
    global $page;
    $page['errors'][] = 'Notify New Photos: folder name is incorrect, uninstall the plugin and rename its folder to "notify_new_photos".';
  }
  return;
}

// +-----------------------------------------------------------------------+
// | Constants                                                             |
// +-----------------------------------------------------------------------+
define('NOTIFY_NEW_PHOTOS_ID', basename(dirname(__FILE__)));
define('NOTIFY_NEW_PHOTOS_PATH', PHPWG_PLUGINS_PATH . NOTIFY_NEW_PHOTOS_ID . '/');
define('NOTIFY_NEW_PHOTOS_REALPATH', realpath(NOTIFY_NEW_PHOTOS_PATH));
define('NOTIFY_NEW_PHOTOS_ADMIN', get_root_url() . 'admin.php?page=plugin-' . NOTIFY_NEW_PHOTOS_ID);
define('NOTIFY_NEW_PHOTOS_CONF', 'notify_new_photos');

// +-----------------------------------------------------------------------+
// | Hooks                                                                 |
// +-----------------------------------------------------------------------+
add_event_handler('init', 'notify_new_photos_init');
add_event_handler('get_admin_plugin_menu_links', 'notify_new_photos_admin_menu');
// ws methods are only needed when ws.php is hit -> lazy-load their definitions.
add_event_handler(
  'ws_add_methods',
  'notify_new_photos_ws_add_methods',
  EVENT_HANDLER_PRIORITY_NEUTRAL,
  NOTIFY_NEW_PHOTOS_PATH . 'include/ws_functions.inc.php'
);

/**
 * Loads the plugin language and, when due, fires the throttled background
 * check (a "poor-man's cron" that only runs on site traffic). The heavy code
 * in include/functions.inc.php is loaded only when the check is actually due.
 *
 * For a guaranteed cadence regardless of traffic, point a real system cron at
 * the web service:
 *   *\/5 * * * * curl -s "https://your-gallery/ws.php?method=notify_new_photos.run&format=json"
 */
function notify_new_photos_init()
{
  global $conf;

  load_language('plugin.lang', NOTIFY_NEW_PHOTOS_PATH);

  $cfg = isset($conf[NOTIFY_NEW_PHOTOS_CONF]) ? safe_unserialize($conf[NOTIFY_NEW_PHOTOS_CONF]) : null;
  if (!is_array($cfg) or empty($cfg['enabled']))
  {
    return;
  }

  $interval = max(1, (int)($cfg['interval'] ?? 5)) * 60;
  $last_run = !empty($cfg['last_run']) ? $cfg['last_run'] : '1970-01-01 00:00:00';
  if ((time() - strtotime($last_run)) < $interval)
  {
    return; // not due yet
  }

  include_once(NOTIFY_NEW_PHOTOS_PATH . 'include/functions.inc.php');
  notify_new_photos_run(false, false);
}

/**
 * Adds the "Settings" entry under Plugins.
 */
function notify_new_photos_admin_menu($menu)
{
  $menu[] = array(
    'NAME' => l10n('Notify New Photos'),
    'URL'  => NOTIFY_NEW_PHOTOS_ADMIN,
  );
  return $menu;
}
