<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

class new_photos_notifier_maintain extends PluginMaintain
{
  function __construct($plugin_id)
  {
    parent::__construct($plugin_id);
  }

  /**
   * Plugin install
   */
  function install($plugin_version, &$errors = array())
  {
    global $conf;
    
    // Initialize last check timestamp to current database time
    if (!isset($conf['new_photos_notifier_last_check']))
    {
      list($dbnow) = pwg_db_fetch_row(pwg_query('SELECT NOW();'));
      conf_update_param('new_photos_notifier_last_check', $dbnow, true);
    }
  }

  /**
   * Plugin activate
   */
  function activate($plugin_version, &$errors = array())
  {
    global $conf;
    
    // Ensure it is initialized when activated
    if (!isset($conf['new_photos_notifier_last_check']))
    {
      list($dbnow) = pwg_db_fetch_row(pwg_query('SELECT NOW();'));
      conf_update_param('new_photos_notifier_last_check', $dbnow, true);
    }
  }

  /**
   * Plugin deactivate
   */
  function deactivate()
  {
    // Nothing special on deactivation
  }

  /**
   * Plugin update
   */
  function update($old_version, $new_version, &$errors = array())
  {
    $this->install($new_version, $errors);
  }

  /**
   * Plugin uninstallation
   */
  function uninstall()
  {
    // Clean up config parameter
    conf_delete_param('new_photos_notifier_last_check');
  }
}
