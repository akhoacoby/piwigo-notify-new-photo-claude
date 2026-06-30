<?php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

/**
 * Checks for new photos and triggers email notification if any are found.
 *
 * @param bool $force If true, ignores the 5-minute interval check.
 * @return array Status report of the operation.
 */
function new_photos_notifier_check($force = false)
{
  global $conf;

  // Retrieve current database time to synchronize timezone
  list($dbnow) = pwg_db_fetch_row(pwg_query('SELECT NOW();'));
  
  if (empty($conf['new_photos_notifier_last_check']))
  {
    $last_check = date('Y-m-d H:i:s', strtotime($dbnow) - 300);
  }
  else
  {
    $last_check = $conf['new_photos_notifier_last_check'];
  }

  $now_ts = strtotime($dbnow);
  $last_check_ts = strtotime($last_check);

  // If not forcing, and 5 minutes haven't passed, skip
  if (!$force && ($now_ts - $last_check_ts < 300))
  {
    return array(
      'status' => 'skipped',
      'reason' => 'Interval not met',
      'last_check' => $last_check,
      'dbnow' => $dbnow,
      'seconds_remaining' => 300 - ($now_ts - $last_check_ts)
    );
  }

  // Update last check parameter immediately to prevent concurrent executions
  conf_update_param('new_photos_notifier_last_check', $dbnow, true);

  // Fetch photos available after the last check timestamp
  $query = '
    SELECT id, path, file, representative_ext, width, height, rotation, name, author, date_available
      FROM '.IMAGES_TABLE.'
      WHERE date_available > \''.$last_check.'\'
      ORDER BY date_available DESC
  ;';
  $new_photos = query2array($query);

  if (empty($new_photos))
  {
    return array(
      'status' => 'no_new_photos',
      'last_check' => $last_check,
      'dbnow' => $dbnow
    );
  }

  // Fetch user 1
  $user_info = new_photos_notifier_get_user_info(1);
  if (!$user_info || empty($user_info['email']))
  {
    return array(
      'status' => 'error',
      'message' => 'User with ID 1 does not exist or has no email address.'
    );
  }

  $sent = new_photos_notifier_send_email($user_info, $new_photos);

  return array(
    'status' => $sent ? 'success' : 'email_failed',
    'recipient' => $user_info['email'],
    'count' => count($new_photos),
    'last_check' => $last_check,
    'dbnow' => $dbnow
  );
}

/**
 * Fetches identity information for a specific user ID.
 *
 * @param int $user_id
 * @return array|null User info or null if not found.
 */
function new_photos_notifier_get_user_info($user_id)
{
  global $conf;

  $query = '
    SELECT 
      '.$conf['user_fields']['id'].' AS id,
      '.$conf['user_fields']['username'].' AS username,
      '.$conf['user_fields']['email'].' AS email
      FROM '.USERS_TABLE.'
      WHERE '.$conf['user_fields']['id'].' = '.(int)$user_id.'
  ;';
  $result = query2array($query);

  if (!empty($result))
  {
    return $result[0];
  }

  return null;
}

/**
 * Formats and sends a premium HTML email using Piwigo's native mail engine.
 *
 * @param array $user_info Recipient user information.
 * @param array $new_photos List of new photos.
 * @return bool True if mail sent successfully.
 */
function new_photos_notifier_send_email($user_info, $new_photos)
{
  global $conf;

  $username = htmlspecialchars($user_info['username']);
  $recipient_email = $user_info['email'];
  $count = count($new_photos);
  $subject = "[Piwigo] " . $count . " " . ($count > 1 ? "nouvelles photos ajoutées !" : "nouvelle photo ajoutée !");

  $photo_cards = '';
  
  // Enable absolute URL generation for email links/thumbnails
  set_make_full_url();

  foreach ($new_photos as $photo)
  {
    $thumb_url = DerivativeImage::thumb_url($photo);
    $page_url = make_picture_url(array(
      'image_id' => $photo['id'],
      'image_file' => $photo['file']
    ));

    $title = !empty($photo['name']) ? $photo['name'] : $photo['file'];
    $author = !empty($photo['author']) ? $photo['author'] : 'Anonyme';
    $date_avail = format_date($photo['date_available']);

    // Inline style card layout
    $photo_cards .= '
    <a href="' . htmlspecialchars($page_url) . '" style="display: block; text-decoration: none; color: #1e293b; background-color: #f8fafc; border: 1px solid #e2e8f0; border-left: 4px solid #4f46e5; border-radius: 12px; padding: 16px; margin-bottom: 16px;">
      <table border="0" cellpadding="0" cellspacing="0" width="100%">
        <tr>
          <td width="96" valign="top">
            <img src="' . htmlspecialchars($thumb_url) . '" width="80" height="80" style="display: block; border-radius: 8px; object-fit: cover; background-color: #e2e8f0;" alt="' . htmlspecialchars($title) . '">
          </td>
          <td valign="top" style="padding-left: 8px;">
            <h3 style="margin: 0 0 6px 0; font-size: 16px; font-weight: 600; color: #0f172a; line-height: 1.4;">' . htmlspecialchars($title) . '</h3>
            <p style="margin: 0; font-size: 13px; color: #64748b; line-height: 1.5;">Par <strong>' . htmlspecialchars($author) . '</strong> &bull; ' . $date_avail . '</p>
          </td>
        </tr>
      </table>
    </a>';
  }

  $gallery_url = get_gallery_home_url();
  unset_make_full_url();

  $year = date('Y');

  $html_body = '
<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
</head>
<body style="font-family: \'Inter\', -apple-system, BlinkMacSystemFont, \'Segoe UI\', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 40px 0; -webkit-font-smoothing: antialiased;">
  <div style="max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 16px; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05); border: 1px solid #e2e8f0; overflow: hidden;">
    <div style="background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%); padding: 32px 24px; text-align: center; color: #ffffff;">
      <h1 style="margin: 0; font-size: 24px; font-weight: 700; letter-spacing: -0.025em; color: #ffffff;">' . ($count > 1 ? 'Nouvelles photos disponibles !' : 'Nouvelle photo disponible !') . '</h1>
      <p style="margin: 8px 0 0 0; font-size: 14px; color: #e0e7ff;">Votre galerie Piwigo</p>
    </div>
    <div style="padding: 32px 24px;">
      <p style="font-size: 16px; line-height: 1.6; color: #475569; margin-bottom: 20px; margin-top: 0;">Bonjour ' . $username . ',</p>
      <p style="font-size: 15px; line-height: 1.6; color: #475569; margin-bottom: 24px;">De nouvelles photos ont été ajoutées à la galerie. Découvrez-les ci-dessous :</p>
      
      <div style="margin-bottom: 32px;">
        ' . $photo_cards . '
      </div>
      
      <div style="text-align: center; margin-top: 24px;">
        <a href="' . htmlspecialchars($gallery_url) . '" style="display: inline-block; background-color: #4f46e5; color: #ffffff !important; text-decoration: none; font-weight: 600; padding: 12px 32px; border-radius: 8px; font-size: 15px;">Visiter la galerie</a>
      </div>
    </div>
    <div style="background-color: #f8fafc; border-top: 1px solid #f1f5f9; padding: 24px; text-align: center; font-size: 12px; color: #94a3b8;">
      <p style="margin: 4px 0;">Cet email a été envoyé automatiquement par votre galerie Piwigo.</p>
      <p style="margin: 4px 0;">&copy; ' . $year . ' Piwigo. Tous droits réservés.</p>
    </div>
  </div>
</body>
</html>';

  include_once(PHPWG_ROOT_PATH . 'include/functions_mail.inc.php');
  $args = array(
    'subject' => $subject,
    'content' => $html_body,
    'content_format' => 'text/html',
    'email_format' => 'text/html',
  );

  return pwg_mail($recipient_email, $args);
}
