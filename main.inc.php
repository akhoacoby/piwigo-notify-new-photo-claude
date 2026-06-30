<?php
/*
Version: 1.0.0
Plugin Name: New Photos Notifier
Plugin URI:
Author: Antigravity
Author URI:
Description: Checks for new photos every 5 minutes and notifies the user (defaulting to user ID 1) by email.
Has Settings: false
*/

if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

// check root directory name
if (basename(dirname(__FILE__)) != 'new_photos_notifier')
{
  add_event_handler('init', 'new_photos_notifier_error');
  function new_photos_notifier_error()
  {
    global $page;
    $page['errors'][] = 'New Photos Notifier plugin folder name is incorrect, uninstall the plugin and rename it to "new_photos_notifier"';
  }
  return;
}

// +-----------------------------------------------------------------------+
// | Define plugin constants                                               |
// +-----------------------------------------------------------------------+
define('NEW_PHOTOS_NOTIFIER_ID', basename(dirname(__FILE__)));
define('NEW_PHOTOS_NOTIFIER_PATH', PHPWG_PLUGINS_PATH . NEW_PHOTOS_NOTIFIER_ID . '/');

// +-----------------------------------------------------------------------+
// | Hook handlers                                                         |
// +-----------------------------------------------------------------------+
add_event_handler('init', 'new_photos_notifier_init');
add_event_handler('ws_add_methods', 'new_photos_notifier_ws_add_methods');

function new_photos_notifier_init()
{
  global $conf;

  // Don't run automatic check during test script execution
  if (defined('TESTING_NOTIFIER'))
  {
    return;
  }

  // Quick timestamp check with virtually zero overhead
  $now = time();
  $last_check = isset($conf['new_photos_notifier_last_check']) ? strtotime($conf['new_photos_notifier_last_check']) : 0;

  // Default to 5 minutes ago if not set, to avoid massive initial query
  if ($last_check === 0)
  {
    $last_check = $now - 300;
    conf_update_param('new_photos_notifier_last_check', date('Y-m-d H:i:s', $last_check), true);
  }

  if ($now - $last_check >= 300)
  {
    include_once(NEW_PHOTOS_NOTIFIER_PATH . 'include/functions.inc.php');
    new_photos_notifier_check(false);
  }
}

function new_photos_notifier_ws_add_methods($arr)
{
  $service = &$arr[0];
  $service->addMethod(
    'new_photos_notifier.force',
    'ws_new_photos_notifier_force',
    array(),
    'Force checking and sending email notification for new photos since the last check.',
    null,
    array('admin_only' => true)
  );
}

function ws_new_photos_notifier_force($params, &$service)
{
  include_once(NEW_PHOTOS_NOTIFIER_PATH . 'include/functions.inc.php');
  return new_photos_notifier_check(true);
}
