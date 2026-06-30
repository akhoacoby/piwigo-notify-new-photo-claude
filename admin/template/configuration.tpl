{* ------------------------------------------------------------------ *}
{* Notify New Photos — settings. Reuses ADMIN-theme classes (no custom *}
{* CSS): #configContent, fieldset+legend, .font-checkbox, .sub-setting, *}
{* fixed .savebar-footer with .buttonLike + .badge.info-message.        *}
{* Auto-escape is OFF — every dynamic value is escaped.                 *}
{* ------------------------------------------------------------------ *}

<form method="post" action="{$F_ACTION}" class="properties">
<div id="configContent">

  <fieldset class="nnpConf">
    <legend><span class="icon-bell icon-blue rotate-element"></span>{'Notifications'|@translate}</legend>
    <ul>
      <li>
        <label class="font-checkbox">
          <span class="icon-check"></span>
          <input type="checkbox" name="enabled" id="nnp_enabled"{if $cfg.enabled} checked="checked"{/if}>
          {'Enable automatic notifications'|@translate}
        </label>
        <span class="icon-help-circled tiptip" title="{'When enabled, the gallery checks for new photos on the interval below (triggered by site traffic). For a guaranteed schedule, call the web service from a system cron.'|@translate}" style="cursor:help"></span>
      </li>

      <li>
        <label class="no-bold">
          {'Check interval (minutes)'|@translate}
          <input type="number" name="interval" min="1" max="1440" value="{$cfg.interval|@intval}">
        </label>
      </li>
    </ul>
  </fieldset>

  <fieldset class="nnpConf">
    <legend><span class="icon-users icon-green rotate-element"></span>{'Recipients'|@translate}</legend>
    <ul>
      <li>
        <label class="no-bold">{'Users to notify'|@translate}</label><br>
        <select name="user_ids[]" multiple="multiple" size="8" style="min-width:260px">
          {foreach from=$all_users item=u}
            <option value="{$u.id|@intval}"{if isset($selected_users[$u.id])} selected="selected"{/if}>{$u.username|@escape}</option>
          {/foreach}
        </select>
      </li>

      <li>
        <label class="no-bold">{'Groups to notify'|@translate}</label><br>
        <select name="group_ids[]" multiple="multiple" size="6" style="min-width:260px">
          {foreach from=$all_groups item=g}
            <option value="{$g.id|@intval}"{if isset($selected_groups[$g.id])} selected="selected"{/if}>{$g.name|@escape}</option>
          {/foreach}
        </select>
        <span class="icon-help-circled tiptip" title="{'Every member of the selected groups is notified.'|@translate}" style="cursor:help"></span>
      </li>

      <li>
        <label class="font-checkbox">
          <span class="icon-check"></span>
          <input type="checkbox" name="include_admins"{if $cfg.include_admins} checked="checked"{/if}>
          {'Also notify all administrators'|@translate}
        </label>
      </li>
      <li><em class="no-bold">{'Each recipient only receives the photos they are allowed to see.'|@translate}</em></li>
    </ul>
  </fieldset>

  <fieldset class="nnpConf">
    <legend><span class="icon-mail icon-purple rotate-element"></span>{'Email'|@translate}</legend>
    <ul>
      <li>
        <label class="font-checkbox">
          <span class="icon-check"></span>
          <input type="radio" name="email_format" value="html"{if $cfg.email_format != 'text'} checked="checked"{/if}>
          {'HTML (with thumbnails)'|@translate}
        </label>
        &nbsp;
        <label class="font-checkbox">
          <span class="icon-check"></span>
          <input type="radio" name="email_format" value="text"{if $cfg.email_format == 'text'} checked="checked"{/if}>
          {'Plain text'|@translate}
        </label>
      </li>
      <li>
        <label class="no-bold">
          {'Maximum thumbnails per email'|@translate}
          <input type="number" name="max_thumbs" min="1" max="50" value="{$cfg.max_thumbs|@intval}">
        </label>
      </li>
    </ul>
  </fieldset>

  <fieldset class="nnpConf">
    <legend><span class="icon-cog icon-yellow rotate-element"></span>{'Status &amp; actions'|@translate}</legend>
    <ul>
      <li><label class="no-bold">{'Last run'|@translate}: <strong>{if $LAST_RUN}{$LAST_RUN|@escape}{else}{'never'|@translate}{/if}</strong></label></li>
      <li>
        <button class="buttonLike" type="submit" name="nnp_run"{if $isWebmaster != 1} disabled{/if}>
          <i class="icon-paper-plane"></i> {'Run now'|@translate}
        </button>
        <button class="buttonLike" type="submit" name="nnp_test"{if $isWebmaster != 1} disabled{/if}>
          <i class="icon-mail"></i> {'Send a test email to me'|@translate}
        </button>
      </li>
    </ul>
  </fieldset>

</div>{* #configContent *}

<div class="savebar-footer">
  <div class="savebar-footer-start"></div>
  <div class="savebar-footer-end">
{if isset($save_success)}
    <div class="savebar-footer-block">
      <div class="badge info-message"><i class="icon-ok"></i>{$save_success|@escape}</div>
    </div>
{/if}
    <div class="savebar-footer-block">
      <button class="buttonLike" type="submit" name="nnp_save"{if $isWebmaster != 1} disabled{/if}>
        <i class="icon-floppy"></i> {'Save Settings'|@translate}
      </button>
    </div>
  </div>
  <input type="hidden" name="pwg_token" value="{$PWG_TOKEN}">
</div>
</form>
