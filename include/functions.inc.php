<?php
if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');

// Core notification helpers (new_elements) + the mailer are not loaded at
// bootstrap; pull them in wherever this file is used.
include_once(PHPWG_ROOT_PATH . 'include/functions_notification.inc.php');

/**
 * @return array the plugin configuration, unserialised, with defaults applied.
 */
function notify_new_photos_get_conf()
{
  global $conf;

  $defaults = array(
    'enabled'        => true,
    'interval'       => 5,
    'user_ids'       => array(1),
    'group_ids'      => array(),
    'include_admins' => false,
    'email_format'   => 'html',
    'max_thumbs'     => 12,
    'last_run'       => date('Y-m-d H:i:s'),
  );

  $cfg = isset($conf[NOTIFY_NEW_PHOTOS_CONF]) ? safe_unserialize($conf[NOTIFY_NEW_PHOTOS_CONF]) : null;
  if (!is_array($cfg))
  {
    $cfg = array();
  }
  $cfg = array_merge($defaults, $cfg);

  // normalise types
  $cfg['user_ids']  = notify_new_photos_int_list($cfg['user_ids']);
  $cfg['group_ids'] = notify_new_photos_int_list($cfg['group_ids']);
  $cfg['interval']  = max(1, (int)$cfg['interval']);
  $cfg['max_thumbs'] = max(1, (int)$cfg['max_thumbs']);
  if (!in_array($cfg['email_format'], array('html', 'text'), true))
  {
    $cfg['email_format'] = 'html';
  }

  $conf[NOTIFY_NEW_PHOTOS_CONF] = $cfg;
  return $cfg;
}

/**
 * @return int[] a clean list of unique positive integers.
 */
function notify_new_photos_int_list($value)
{
  $out = array();
  foreach ((array)$value as $v)
  {
    if (is_numeric($v) and (int)$v > 0)
    {
      $out[(int)$v] = (int)$v;
    }
  }
  return array_values($out);
}

/**
 * Resolves the configured users + groups (+ admins) into one deduplicated
 * list of user ids (the guest user is never notified).
 *
 * @return int[]
 */
function notify_new_photos_resolve_recipient_ids($cfg)
{
  global $conf;

  $ids = $cfg['user_ids'];

  if (!empty($cfg['group_ids']))
  {
    $ids = array_merge($ids, query2array('
SELECT user_id
  FROM '.USER_GROUP_TABLE.'
  WHERE group_id IN ('.implode(',', $cfg['group_ids']).')
;', null, 'user_id'));
  }

  if (!empty($cfg['include_admins']))
  {
    $ids = array_merge($ids, query2array('
SELECT user_id
  FROM '.USER_INFOS_TABLE.'
  WHERE status IN (\'webmaster\',\'admin\')
;', null, 'user_id'));
  }

  $ids = notify_new_photos_int_list($ids);

  // never mail the anonymous/guest account
  $guest_id = (int)$conf['guest_id'];
  $ids = array_values(array_filter($ids, function($id) use ($guest_id) { return $id !== $guest_id; }));

  return $ids;
}

/**
 * Main entry point: for every recipient, find the photos they are allowed to
 * see that were added since the last run, and email them.
 *
 * @param bool $force   bypass the enabled flag and the interval throttle
 * @param bool $dry_run report what would happen without sending or advancing
 *                      the boundary
 * @return array diagnostic summary
 */
function notify_new_photos_run($force = false, $dry_run = false)
{
  global $user, $conf;

  $cfg = notify_new_photos_get_conf();

  if (!$force and empty($cfg['enabled']))
  {
    return array('skipped' => true, 'reason' => 'disabled');
  }

  $interval = $cfg['interval'] * 60;
  $elapsed = time() - strtotime($cfg['last_run']);
  if (!$force and $elapsed < $interval)
  {
    return array(
      'skipped'   => true,
      'reason'    => 'throttled',
      'last_run'  => $cfg['last_run'],
      'next_in_s' => $interval - $elapsed,
    );
  }

  $start = $cfg['last_run'];
  $now   = date('Y-m-d H:i:s');

  $recipient_ids = notify_new_photos_resolve_recipient_ids($cfg);

  $summary = array(
    'forced'     => $force,
    'dry_run'    => $dry_run,
    'since'      => $start,
    'until'      => $now,
    'recipients' => count($recipient_ids),
    'sent'       => array(),
  );

  if (!empty($recipient_ids))
  {
    include_once(PHPWG_ROOT_PATH . 'include/functions_mail.inc.php');

    // Save the page context once (NBM pattern): $user + the current language.
    $saved_user = $user;
    switch_lang_to($user['language']);

    foreach ($recipient_ids as $uid)
    {
      // Become this recipient so new_elements() / FandF reflect THEIR rights.
      $user = build_user($uid, true);
      if (empty($user['email']))
      {
        continue;
      }
      switch_lang_to($user['language']);

      // new_elements() returns rows shaped [{image_id: N}, ...]; flatten to ids.
      $image_ids = array_column(new_elements($start, $now), 'image_id');
      $count = count($image_ids);
      if ($count > 0)
      {
        if (!$dry_run)
        {
          notify_new_photos_send_mail($image_ids, $count, $cfg);
        }
        $summary['sent'][] = array(
          'user_id' => $uid,
          'email'   => $user['email'],
          'count'   => $count,
        );
      }

      switch_lang_back();
    }

    // Restore the page context.
    $user = $saved_user;
    switch_lang_back();
  }

  if (!$dry_run)
  {
    $cfg['last_run'] = $now;
    conf_update_param(NOTIFY_NEW_PHOTOS_CONF, $cfg, true);
  }

  return $summary;
}

/**
 * Sends a test email to the CURRENT user, using whatever recent photos they
 * are allowed to see (so the admin can preview the real layout). Assumes the
 * caller is the logged-in admin.
 *
 * @return array
 */
function notify_new_photos_send_test($cfg)
{
  global $user, $conf;

  if (empty($user['email']))
  {
    return array('sent' => false, 'reason' => 'no_email');
  }

  include_once(PHPWG_ROOT_PATH . 'include/functions_mail.inc.php');

  $start = date('Y-m-d H:i:s', strtotime('-1 year'));
  $now   = date('Y-m-d H:i:s');
  $image_ids = array_column(new_elements($start, $now), 'image_id');
  $count = count($image_ids);

  $intro = l10n('This is a test of the new-photos notification.');
  $rows  = notify_new_photos_image_rows($image_ids, $cfg['max_thumbs']);

  set_make_full_url();
  $content = notify_new_photos_build_html($rows, $count, $intro);
  unset_make_full_url();

  pwg_mail(
    array('name' => $user['username'], 'email' => $user['email']),
    array(
      'subject'        => '['.$conf['gallery_title'].'] '.l10n('Test notification'),
      'content'        => $content,
      'content_format' => 'text/html',
      'email_format'   => ($cfg['email_format'] === 'text') ? 'text/plain' : null,
    )
  );

  return array('sent' => true, 'email' => $user['email'], 'count' => $count);
}

/**
 * Builds and sends one notification to the CURRENT user (caller must already
 * have swapped $user + language to the recipient).
 */
function notify_new_photos_send_mail($image_ids, $count, $cfg)
{
  global $user, $conf;

  $rows = notify_new_photos_image_rows($image_ids, $cfg['max_thumbs']);

  $intro = l10n_dec(
    '%d new photo has been added to the gallery:',
    '%d new photos have been added to the gallery:',
    $count
  );

  set_make_full_url();
  $content = notify_new_photos_build_html($rows, $count, $intro);
  unset_make_full_url();

  pwg_mail(
    array('name' => $user['username'], 'email' => $user['email']),
    array(
      'subject'        => '['.$conf['gallery_title'].'] '.l10n_dec('%d new photo', '%d new photos', $count),
      'content'        => $content,
      'content_format' => 'text/html',
      'email_format'   => ($cfg['email_format'] === 'text') ? 'text/plain' : null,
    )
  );
}

/**
 * Loads the image rows needed for thumbnails, newest first, capped at $limit.
 * Ids are assumed already permission-checked by new_elements().
 *
 * @return array
 */
function notify_new_photos_image_rows($image_ids, $limit)
{
  $ids = notify_new_photos_int_list($image_ids);
  if (empty($ids))
  {
    return array();
  }
  $limit = max(1, (int)$limit);

  return query2array('
SELECT id, name, file, path, width, height, representative_ext, rotation
  FROM '.IMAGES_TABLE.'
  WHERE id IN ('.implode(',', $ids).')
  ORDER BY date_available DESC
  LIMIT '.$limit.'
;');
}

/**
 * Builds the HTML email body (auto-converted to plain text by pwg_mail for the
 * text format). Everything dynamic is HTML-escaped at output.
 *
 * @param array  $rows  image rows (id, name, file, path, ...)
 * @param int    $total total number of new photos (>= count($rows))
 * @param string $intro leading sentence
 * @return string
 */
function notify_new_photos_build_html($rows, $total, $intro)
{
  $charset = get_pwg_charset();
  $esc = function($s) use ($charset) { return htmlspecialchars((string)$s, ENT_QUOTES, $charset); };

  $per_row  = 3;
  $thumb_px = 144;

  $html = '<p>'.$esc($intro).'</p>';

  if (!empty($rows))
  {
    $html .= '<table cellspacing="0" cellpadding="0" style="border-collapse:collapse"><tr>';
    $i = 0;
    foreach ($rows as $row)
    {
      $name  = !empty($row['name']) ? $row['name'] : get_name_from_file($row['file']);
      $thumb = DerivativeImage::thumb_url($row);
      $purl  = make_picture_url(array('image_id' => $row['id'], 'image_file' => $row['file']));

      $html .=
        '<td style="padding:6px;text-align:center;vertical-align:top">'
        .'<a href="'.$esc($purl).'" style="text-decoration:none;color:#333">'
        .'<img src="'.$esc($thumb).'" alt="'.$esc($name).'" width="'.$thumb_px.'" '
        .'style="display:block;border:0;border-radius:4px;margin:0 auto 4px">'
        .'<span style="font-size:12px">'.$esc($name).'</span>'
        .'</a></td>';

      if (++$i % $per_row == 0)
      {
        $html .= '</tr><tr>';
      }
    }
    $html .= '</tr></table>';
  }

  $more = $total - count($rows);
  if ($more > 0)
  {
    $html .= '<p>'.$esc(l10n_dec('… and %d more photo', '… and %d more photos', $more)).'</p>';
  }

  $html .= '<p><a href="'.$esc(get_gallery_home_url()).'">'.$esc(l10n('View the gallery')).'</a></p>';

  return $html;
}
