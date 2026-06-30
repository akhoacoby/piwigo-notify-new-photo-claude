<?php
if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

global $template, $conf, $page, $user;

check_status(ACCESS_ADMINISTRATOR);

include_once(NOTIFY_NEW_PHOTOS_PATH . 'include/functions.inc.php');

$page['tab'] = 'config';

$cfg = notify_new_photos_get_conf();

// +-----------------------------------------------------------------------+
// | Actions                                                               |
// +-----------------------------------------------------------------------+
if (isset($_POST['nnp_save']))
{
  check_pwg_token();

  $cfg['enabled']        = isset($_POST['enabled']);
  $cfg['include_admins'] = isset($_POST['include_admins']);
  $cfg['interval']       = max(1, min(1440, (int)($_POST['interval'] ?? 5)));
  $cfg['max_thumbs']     = max(1, min(50, (int)($_POST['max_thumbs'] ?? 12)));
  $cfg['email_format']   = in_array(($_POST['email_format'] ?? ''), array('html', 'text'), true)
                             ? $_POST['email_format'] : 'html';
  $cfg['user_ids']       = notify_new_photos_int_list($_POST['user_ids'] ?? array());
  $cfg['group_ids']      = notify_new_photos_int_list($_POST['group_ids'] ?? array());

  conf_update_param(NOTIFY_NEW_PHOTOS_CONF, $cfg, true);
  $template->assign('save_success', l10n('Settings saved'));
}
elseif (isset($_POST['nnp_run']))
{
  check_pwg_token();

  $res = notify_new_photos_run(true, false);
  $cfg = notify_new_photos_get_conf(); // reload: last_run advanced
  $nb_sent = count($res['sent'] ?? array());
  $page['infos'][] = l10n('Notification run complete: %d email(s) sent.', $nb_sent);
}
elseif (isset($_POST['nnp_test']))
{
  check_pwg_token();

  $res = notify_new_photos_send_test($cfg);
  if (!empty($res['sent']))
  {
    $page['infos'][] = l10n('Test email sent to %s.', $user['email']);
  }
  else
  {
    $page['errors'][] = l10n('Could not send the test email: your account has no email address.');
  }
}

// +-----------------------------------------------------------------------+
// | Pickers                                                               |
// +-----------------------------------------------------------------------+
$all_users = query2array('
SELECT '.$conf['user_fields']['id'].' AS id, '.$conf['user_fields']['username'].' AS username
  FROM '.USERS_TABLE.'
  ORDER BY '.$conf['user_fields']['username'].'
;');

$all_groups = query2array('
SELECT id, name
  FROM '.GROUPS_TABLE.'
  ORDER BY name
;');

// +-----------------------------------------------------------------------+
// | Tabsheet                                                              |
// +-----------------------------------------------------------------------+
include_once(PHPWG_ROOT_PATH . 'admin/include/tabsheet.class.php');
$tabsheet = new tabsheet();
$tabsheet->set_id('notify_new_photos_tab');
$tabsheet->add('config', '<span class="icon-cog"></span>'.l10n('Configuration'), NOTIFY_NEW_PHOTOS_ADMIN.'-config');
$tabsheet->select($page['tab']);
$tabsheet->assign();

// +-----------------------------------------------------------------------+
// | Render                                                                |
// +-----------------------------------------------------------------------+
$template->assign(array(
  'F_ACTION'        => NOTIFY_NEW_PHOTOS_ADMIN.'-config',
  'PWG_TOKEN'       => get_pwg_token(),
  'isWebmaster'     => is_webmaster() ? 1 : 0,
  'cfg'             => $cfg,
  'all_users'       => $all_users,
  'all_groups'      => $all_groups,
  'selected_users'  => array_fill_keys($cfg['user_ids'], true),
  'selected_groups' => array_fill_keys($cfg['group_ids'], true),
  'LAST_RUN'        => $cfg['last_run'],
));

$template->set_filename('notify_new_photos_content', NOTIFY_NEW_PHOTOS_REALPATH.'/admin/template/configuration.tpl');
$template->assign_var_from_handle('ADMIN_CONTENT', 'notify_new_photos_content');
