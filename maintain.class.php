<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

class notify_new_photos_maintain extends PluginMaintain
{
  const CONF = 'notify_new_photos';

  private $default_conf = array(
    'enabled'        => true,
    'interval'       => 5,            // minutes between automatic checks
    'user_ids'       => array(1),     // explicitly selected users
    'group_ids'      => array(),      // every member of these groups
    'include_admins' => false,        // also every admin / webmaster
    'email_format'   => 'html',       // 'html' | 'text'
    'max_thumbs'     => 12,           // thumbnails shown in the email
    'last_run'       => null,         // boundary + throttle (set to "now" on install)
  );

  function __construct($plugin_id)
  {
    parent::__construct($plugin_id);
  }

  /**
   * Seed / migrate the config blob. Merging keeps the user's existing values
   * while adding any new keys introduced by an update. last_run starts at
   * "now" so the first run never emails about the whole existing library.
   */
  function install($plugin_version, &$errors = array())
  {
    $raw = conf_get_param(self::CONF, null);
    $current = ($raw === null) ? array() : safe_unserialize($raw);
    if (!is_array($current))
    {
      $current = array();
    }

    $cfg = array_merge($this->default_conf, $current);
    if (empty($cfg['last_run']))
    {
      $cfg['last_run'] = date('Y-m-d H:i:s');
    }

    conf_update_param(self::CONF, $cfg, true);
  }

  function activate($plugin_version, &$errors = array())
  {
    $this->install($plugin_version, $errors);
  }

  function deactivate()
  {
  }

  function update($old_version, $new_version, &$errors = array())
  {
    $this->install($new_version, $errors);
  }

  /**
   * Remove only our own config key.
   */
  function uninstall()
  {
    conf_delete_param(self::CONF);
  }
}
