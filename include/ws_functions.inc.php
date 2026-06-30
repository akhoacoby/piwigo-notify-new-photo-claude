<?php
if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

/**
 * Registers the plugin's web-service methods (event: ws_add_methods).
 */
function notify_new_photos_ws_add_methods($arr)
{
  $service = &$arr[0];

  $service->addMethod(
    'notify_new_photos.run',
    'ws_notify_new_photos_run',
    array(
      'force' => array(
        'flags'   => WS_PARAM_OPTIONAL,
        'type'    => WS_TYPE_BOOL,
        'default' => true,
      ),
      'dry_run' => array(
        'flags'   => WS_PARAM_OPTIONAL,
        'type'    => WS_TYPE_BOOL,
        'default' => false,
      ),
    ),
    'Find photos added since the last notification and email the configured recipients.'
      .' force=true ignores the enabled flag and the interval throttle;'
      .' dry_run=true reports who would be notified without sending or advancing the boundary.'
      .' Ideal as a system-cron target.',
    null,
    array('admin_only' => true)
  );

  $service->addMethod(
    'notify_new_photos.test',
    'ws_notify_new_photos_test',
    array(),
    'Send a test notification email to the calling administrator.',
    null,
    array('admin_only' => true)
  );
}

/**
 * notify_new_photos.run
 */
function ws_notify_new_photos_run($params, &$service)
{
  if (!is_admin())
  {
    return new PwgError(403, 'Forbidden');
  }

  include_once(NOTIFY_NEW_PHOTOS_PATH . 'include/functions.inc.php');
  return notify_new_photos_run((bool)$params['force'], (bool)$params['dry_run']);
}

/**
 * notify_new_photos.test
 */
function ws_notify_new_photos_test($params, &$service)
{
  if (!is_admin())
  {
    return new PwgError(403, 'Forbidden');
  }

  include_once(NOTIFY_NEW_PHOTOS_PATH . 'include/functions.inc.php');
  return notify_new_photos_send_test(notify_new_photos_get_conf());
}
