<?php
/**
 * Drafts AutoSave
 *
 * Automatically save messages as drafts once in a while.
 *
 * @package Drafts AutoSave
 * @author  Shade <shad3-@outlook.com>
 * @license © Copyrighted
 * @version 1.1
 */

if (!defined('IN_MYBB')) {
	die('Direct initialization of this file is not allowed.<br /><br />Please make sure IN_MYBB is defined.');
}

if (!defined("PLUGINLIBRARY")) {
	define("PLUGINLIBRARY", MYBB_ROOT . "inc/plugins/pluginlibrary.php");
}

function autosavedrafts_info()
{
	return array(
		'name' => 'Drafts AutoSave',
		'description' => 'Automatically save messages as drafts once in a while.',
		'website' => '',
		'author' => 'Shade',
		'version' => '1.1',
		'compatibility' => '16*,18*',
		'guid' => ''
	);
}

function autosavedrafts_is_installed()
{
	global $cache;

	$info = autosavedrafts_info();
	$installed = $cache->read("shade_plugins");
	if ($installed[$info['name']]) {
		return true;
	}
}

function autosavedrafts_install()
{
	global $db, $mybb, $cache, $PL, $lang;

	if (!$lang->autosavedrafts) {
		$lang->load('autosavedrafts');
	}

	if (!file_exists(PLUGINLIBRARY)) {
		flash_message($lang->autosavedrafts_pluginlibrary_missing, "error");
		admin_redirect("index.php?module=config-plugins");
	}

	// Add the plugin to our cache
	$info = autosavedrafts_info();
	$shadePlugins = $cache->read('shade_plugins');
	$shadePlugins[$info['name']] = array(
		'title' => $info['name'],
		'version' => $info['version']
	);
	$cache->update('shade_plugins', $shadePlugins);

	$PL or require_once PLUGINLIBRARY;

	// Add settings
	$optionscode = "radio \n 1=Don't use messages \n 2=Humane.js";
	$message_value = 2;
	if ($mybb->version_code >= 1700) {
		$optionscode = "radio \n 1=Don't use messages \n 2=Humane.js \n 3=jGrowl";
		$message_value = 3;
	}

	$PL->settings('autosavedrafts', $lang->setting_group_autosavedrafts, $lang->setting_group_autosavedrafts_desc, array(
		'enabled' => array(
			'title' => $lang->setting_autosavedrafts_enable,
			'description' => $lang->setting_autosavedrafts_enable_desc,
			'value' => 1
		),
		'interval' => array(
			'title' => $lang->setting_autosavedrafts_interval,
			'description' => $lang->setting_autosavedrafts_interval_desc,
			'optionscode' => 'text',
			'value' => 60
		),
		'engine' => array(
			'title' => $lang->setting_autosavedrafts_engine,
			'description' => $lang->setting_autosavedrafts_engine_desc,
			'optionscode' => "radio \n 1=PluginLibrary \n 2=Database",
			'value' => 1
		),
		'browser' => array(
			'title' => $lang->setting_autosavedrafts_browser,
			'description' => $lang->setting_autosavedrafts_browser_desc,
			'value' => 1
		),
		'messages' => array(
			'title' => $lang->setting_autosavedrafts_messages,
			'description' => $lang->setting_autosavedrafts_messages_desc,
			'optionscode' => $optionscode,
			'value' => $message_value
		),
		'scroll' => array(
			'title' => $lang->setting_autosavedrafts_scroll,
			'description' => $lang->setting_autosavedrafts_scroll_desc,
			'value' => 0
		),
		'direct_load' => array(
			'title' => $lang->setting_autosavedrafts_direct_load,
			'description' => $lang->setting_autosavedrafts_direct_load_desc,
			'value' => 0
		)
	));
}

function autosavedrafts_uninstall()
{
	global $db, $cache, $PL, $lang;

	if (!$lang->autosavedrafts) {
		$lang->load('autosavedrafts');
	}

	if (!file_exists(PLUGINLIBRARY)) {
		flash_message($lang->autosavedrafts_pluginlibrary_missing, "error");
		admin_redirect("index.php?module=config-plugins");
	}

	$PL or require_once PLUGINLIBRARY;

	$info = autosavedrafts_info();

	// Delete the plugin from cache
	$shadePlugins = $cache->read('shade_plugins');
	unset($shadePlugins[$info['name']]);
	$cache->update('shade_plugins', $shadePlugins);

	// Delete settings
	$PL->settings_delete('autosavedrafts');

	// Drop table
	$db->drop_table('autosaved_drafts');

}

global $mybb;

if ($mybb->settings['autosavedrafts_enabled']) {

	$plugins->add_hook('xmlhttp', 'autosavedrafts_xmlhttp');
	$plugins->add_hook('usercp_drafts_end', 'autosavedrafts_usercp_drafts');
	$plugins->add_hook('newreply_end', 'autosavedrafts_load_draft');
	$plugins->add_hook('newthread_end', 'autosavedrafts_load_draft');
	$plugins->add_hook('pre_output_page', 'autosavedrafts_load_scripts');
	$plugins->add_hook('usercp_do_drafts_start', 'autosavedrafts_delete_drafts');
	$plugins->add_hook('newreply_do_newreply_end', 'autosavedrafts_delete_drafts_on_submit');
	$plugins->add_hook('newthread_do_newthread_end', 'autosavedrafts_delete_drafts_on_submit');

	if (defined('IN_ADMINCP')) {
		$plugins->add_hook("admin_config_settings_change", "autosavedrafts_settings_saver");
	}

}

// Automatically save as drafts incoming data
function autosavedrafts_xmlhttp()
{
	global $mybb, $autosaved_drafts;

	if ($mybb->input['action'] != 'autosave_draft') {
		return;
	}

	if (!trim($mybb->input['message']) or !trim($mybb->input['subject'])) {
		echo $lang->autosavedrafts_error_empty_data;
		return;
	}

	// Sort out the message's type
	if ($mybb->input['fid']) {
		$key = 'thread_' . $mybb->input['fid'];
	}
	else if ($mybb->input['tid']) {
		$key = 'post_' . $mybb->input['tid'];
	}
	else {
		echo 2;
		return;
	}

	$new_thread = array(
		'subject' => htmlspecialchars_uni($mybb->input['subject']),
		'message' => htmlspecialchars_uni($mybb->input['message']),
		'dateline' => TIME_NOW
	);

	if ($mybb->input['tid']) {
		$new_thread['tid'] = (int) $mybb->input['tid'];
	}

	if ($mybb->input['fid']) {
		$new_thread['fid'] = (int) $mybb->input['fid'];
	}

	// Echo the result
	switch ($mybb->settings['autosavedrafts_engine']) {

		// PluginLibrary
		case 1:

			global $PL;

			$PL or require_once PLUGINLIBRARY;

			if (!$autosaved_drafts) {
				$autosaved_drafts = $PL->cache_read('autosaved_drafts');
			}

			$autosaved_drafts[$mybb->user['uid']][$key] = $new_thread;

			if ($PL->cache_update('autosaved_drafts', $autosaved_drafts)) {
				echo 1;
			} else {
				echo 0;
			}

			break;

		// Database
		case 2:

			global $db;

			// Add uid & type
			$new_thread['uid'] = $mybb->user['uid'];
			$new_thread['type'] = $key;

			// No SQL Injections should pass!
			$new_thread['subject'] = $db->escape_string($new_thread['subject']);
			$new_thread['message'] = $db->escape_string($new_thread['message']);

			// Strip out tid and fid
			unset($new_thread['tid'], $new_thread['fid']);

			$prefix = TABLE_PREFIX;
			$keys = implode(",", array_keys($new_thread));
			$values = implode("','", $new_thread);

			// Do it! (with just one, fancy query)
			$query = <<<SQL
				INSERT INTO {$prefix}autosaved_drafts ({$keys})
				VALUES ('{$values}')
				ON DUPLICATE KEY UPDATE
					message = '{$new_thread['message']}',
					subject = '{$new_thread['subject']}',
					dateline = '{$new_thread['dateline']}'
SQL;

			if ($db->write_query($query)) {
				echo 1;
			}
			else {
				echo 0;
			}

			break;

	}

	return;
}

// Display autosaved drafts
function autosavedrafts_usercp_drafts()
{
	global $mybb, $drafts, $count, $draftcount, $disable_delete_drafts, $templates, $lang, $db, $autosaved_drafts;

	if (!$lang->autosavedrafts) {
		$lang->load('autosavedrafts');
	}

	if ($mybb->settings['autosavedrafts_engine'] == 1) {

		global $PL;

		$PL or require_once PLUGINLIBRARY;

		if (!$autosaved_drafts) {
			$autosaved_drafts = $PL->cache_read('autosaved_drafts');
		}

		// No drafts saved for this user
		if (!$autosaved_drafts[$mybb->user['uid']]) {
			return;
		}

	}
	else if ($mybb->settings['autosavedrafts_engine'] == 2) {

		$query = $db->simple_select('autosaved_drafts', '*', "uid = {$mybb->user['uid']}");

		if ($db->num_rows($query) > 0) {
			while ($draft = $db->fetch_array($query)) {
				$autosaved_drafts[$mybb->user['uid']][] = $draft;
			}
		}
		else {
			return;
		}

	}

	// If there are no drafts saved, we must reset the built template
	if ($disable_delete_drafts) {
		$drafts = '';
	}

	$draftcount = $count['draftcount'];

	// Attach our auto saved drafts to the existing ones (if any)
	foreach ($autosaved_drafts[$mybb->user['uid']] as &$draft) {

		$trow = alt_trow();

		// Date/time formatting
		$savedate = my_date($mybb->settings['dateformat'], $draft['dateline']);
		$savetime = my_date($mybb->settings['timeformat'], $draft['dateline']);

		// Coming from the database, we don't have tid/fid and must create them
		if ($draft['type']) {

			// Tid
			if (strpos($draft['type'], 'post_') !== false) {
				$draft['tid'] = str_replace('post_', '', $draft['type']);
			}
			// Fid
			else if (strpos($draft['type'], 'thread_') !== false) {
				$draft['fid'] = str_replace('thread_', '', $draft['type']);
			}

		}

		// Gather our drafts' details
		if ($draft['tid']) {

			$query = $db->simple_select('threads', 'subject', 'tid=' . (int) $draft['tid']);
			$subject = $db->fetch_field($query, 'subject');
			$db->free_result($query);

			$detail = $lang->thread . " <a href=\"" . get_thread_link($draft['tid']) . "\">{$subject}</a>";
			$editurl = "newreply.php?tid={$draft['tid']}";
			$id = $draft['tid'];
			$type = 'auto_post';

		}
		else {

			// get_forum() reads in the cache, we don't need to query the db here
			$forum = get_forum($draft['fid']);

			$detail = $lang->forum . " <a href=\"" . get_forum_link($draft['fid']) . "\">{$forum['name']}</a>";
			$editurl = "newthread.php?fid={$draft['fid']}";
			$id = $draft['fid'];
			$type = 'auto_thread';

		}

		$editurl .= "&autosaved=1";
		$draft['subject'] .= $lang->autosavedrafts_usercp_extra;

		// Build it
		eval("\$drafts .= \"" . $templates->get("usercp_drafts_draft") . "\";");

		// Update total drafts count
		$draftcount++;

	}

	if ($draftcount > 0) {
		$disable_delete_drafts = '';
	}

	$draftcount = "(" . my_number_format($draftcount) . ")";

	return;
}

// Load a custom draft as a new reply/thread
function autosavedrafts_load_draft()
{
	global $mybb, $subject, $message, $autosaved_drafts;

	if (!$mybb->input['autosaved']) {
		return;
	}

	if ($mybb->input['fid']) {
		$key = 'thread_' . (int) $mybb->input['fid'];
	}
	else if ($mybb->input['tid']) {
		$key = 'post_' . (int) $mybb->input['tid'];
	}

	if ($mybb->settings['autosavedrafts_engine'] == 1) {

		global $PL;

		$PL or require_once PLUGINLIBRARY;

		if (!$autosaved_drafts) {
			$autosaved_drafts = $PL->cache_read('autosaved_drafts');
		}

		// No drafts saved for this user
		if (!$autosaved_drafts[$mybb->user['uid']]) {
			return;
		}

	}
	else if ($mybb->settings['autosavedrafts_engine'] == 2) {

		global $db;

		$query = $db->simple_select('autosaved_drafts', 'subject, message', "type = '$key' AND uid = {$mybb->user['uid']}", array(
			'limit' => 1
		));

		if ($db->num_rows($query) > 0) {
			$autosaved_drafts[$mybb->user['uid']][$key] = (array) $db->fetch_array($query);
		}
		else {
			return;
		}

	}

	$subject = $autosaved_drafts[$mybb->user['uid']][$key]['subject'];
	$message = $autosaved_drafts[$mybb->user['uid']][$key]['message'];

	return;
}

// Delete a draft on submit
function autosavedrafts_delete_drafts_on_submit()
{
	global $mybb, $thread, $forum, $autosaved_drafts;

	if (THIS_SCRIPT == 'newreply.php' and $thread['tid']) {
		$key = 'post_' . $thread['tid'];
	}
	else if (THIS_SCRIPT == 'newthread.php' and $forum['fid']) {
		$key = 'thread_' . $forum['fid'];
	}

	if ($mybb->settings['autosavedrafts_engine'] == 1) {

		global $PL;

		$PL or require_once PLUGINLIBRARY;

		if (!$autosaved_drafts) {
			$autosaved_drafts = $PL->cache_read('autosaved_drafts');
		}

		// No drafts saved for this user
		if (!$autosaved_drafts[$mybb->user['uid']]) {
			return;
		}

		unset($autosaved_drafts[$mybb->user['uid']][$key]);

		$PL->cache_update('autosaved_drafts', $autosaved_drafts);

	}
	else if ($mybb->settings['autosavedrafts_engine'] == 2) {

		global $db;

		$db->delete_query('autosaved_drafts', "type = '$key' AND uid = {$mybb->user['uid']}");

	}

	return;
}

// Delete our custom drafts
function autosavedrafts_delete_drafts()
{
	global $mybb, $lang, $autosaved_drafts, $PL;

	$PL or require_once PLUGINLIBRARY;

	foreach ($mybb->input['deletedraft'] as $id => $value) {

		// Autosaved drafts have an unique identifier, just to make sure we are deleting autosaved drafts and nothing else
		if (strpos($value, 'auto') === false) {
			continue;
		}

		if ($value == 'auto_post') {
			$key = 'post_' . (int) $id;
		}
		else {
			$key = 'thread_' . (int) $id;
		}

		if ($mybb->settings['autosavedrafts_engine'] == 1) {

			if (!$autosaved_drafts) {
				$autosaved_drafts = $PL->cache_read('autosaved_drafts');
			}

			unset($autosaved_drafts[$mybb->user['uid']][$key]);

		}
		else if ($mybb->settings['autosavedrafts_engine'] == 2) {

			global $db;

			$db->delete_query('autosaved_drafts', "type = '$key' AND uid = {$mybb->user['uid']}");

		}

		// We don't need this anymore
		unset($mybb->input['deletedraft'][$id]);

	}

	if ($mybb->settings['autosavedrafts_engine'] == 1) {

		if ($autosaved_drafts) {
			$PL->cache_update('autosaved_drafts', $autosaved_drafts);
		}

	}

	// Redirect if no drafts are left. If there are other drafts, they will be handled by the core.
	if (!$mybb->input['deletedraft']) {
		redirect('usercp.php?action=drafts', $lang->selected_drafts_deleted);
	}

	return;
}

// Load our scripts
function autosavedrafts_load_scripts(&$content)
{
	global $mybb, $autosaved_drafts;

	if (!in_array(THIS_SCRIPT, array(
		'newreply.php',
		'newthread.php',
		'showthread.php'
	)) or (!$mybb->usergroup['canpostreplys'] and THIS_SCRIPT == 'showthread.php')) {
		return;
	}

	global $lang;

	if (!$lang->autosavedrafts) {
		$lang->load('autosavedrafts');
	}

	$tid = $fid = '';

	// Fid/tid
	if ($mybb->input['fid']) {

		$fid = (int) $mybb->input['fid'];

		$tempkey = 'thread_' . $fid;

	}
	else if ($mybb->input['tid']) {

		$tid = (int) $mybb->input['tid'];

		$tempkey = 'post_' . $tid;

	}

	// Load the appropriate engines and get the messages
	if (!$autosaved_drafts) {

		if ($mybb->settings['autosavedrafts_engine'] == 1) {

			global $PL;

			$PL or require_once PLUGINLIBRARY;

			$autosaved_drafts = $PL->cache_read('autosaved_drafts');

		}
		else if ($mybb->settings['autosavedrafts_engine'] == 2) {

			global $db;

			$query = $db->simple_select('autosaved_drafts', '*', "type = '$tempkey' AND uid = {$mybb->user['uid']}", array(
				'limit' => 1
			));

			if ($db->num_rows($query) > 0) {
				$autosaved_drafts[$mybb->user['uid']][$tempkey] = (array) $db->fetch_array($query);
			}

		}
	}

	$notice = '';

	if ($autosaved_drafts[$mybb->user['uid']][$tempkey]) {
		$notice = $autosaved_drafts[$mybb->user['uid']][$tempkey];
	}

	// The interval (60 secs = default)
	$interval = $mybb->settings['autosavedrafts_interval'] * 1000;
	if (!$mybb->settings['autosavedrafts_interval']) {
		$interval = 60 * 1000;
	}

	$draft_message = '';
	// Hello! We have a saved message here
	if ($notice and !$mybb->input['autosaved']) {
		$draft_message = addslashes($notice['message']);
	}

	// Store in other arrays our options and language locale which will be passed to the JS later
	$options = array(
		'storage' => (int) $mybb->settings['autosavedrafts_browser'],
		'scroll' => (int) $mybb->settings['autosavedrafts_scroll'],
		'messages' => (int) $mybb->settings['autosavedrafts_messages'],
		'direct_load' => (int) $mybb->settings['autosavedrafts_direct_load']
	);

	$locale = array(
		'success_saved' => addslashes($lang->autosavedrafts_success_saved),
		'error_failed' => addslashes($lang->autosavedrafts_error_failed),
		'error_empty_data' => addslashes($lang->autosavedrafts_error_empty_data),
		'draft_available' => addslashes($lang->autosavedrafts_js_draft_available)
	);

	// Sort out the message engine
	$humane = '';
	if ($mybb->settings['autosavedrafts_messages'] == 2) {
		$humane = "<script type='text/javascript' src='{$mybb->settings['bburl']}/jscripts/humane.min.js'></script>";
	}

	$scripts = <<<HTML
$humane
<script type="text/javascript">
if (typeof jQuery == 'undefined') {
	document.write(unescape("%3Cscript src='http://code.jquery.com/jquery-1.11.1.min.js' type='text/javascript'%3E%3C/script%3E"));
}
</script>
<script type='text/javascript' src='{$mybb->settings['bburl']}/jscripts/autosavedrafts.js'></script>
<script type='text/javascript'>

		AutoSave.options = {
			use_storage: {$options['storage']},
			use_scroll: {$options['scroll']},
			use_messages: {$options['messages']},
			use_direct_load: {$options['direct_load']},
			interval: $interval
		};

		AutoSave.lang = {
			success_saved: '{$locale['success_saved']}',
			error_failed: '{$locale['error_failed']}',
			error_empty_data: '{$locale['error_empty_data']}',
			draft_available: '{$locale['draft_available']}'
		};

		AutoSave.tid = '$tid';
		AutoSave.fid = '$fid';
		AutoSave.draft_message = '$draft_message';

		jQuery(window).load(function() {
			AutoSave.initialize();
		});

</script>
HTML;

	return str_replace('</head>', $scripts . '</head>', $content);
}

// Create or remove tables on engine change
function autosavedrafts_settings_saver()
{
	global $mybb, $page, $db;

	if ($mybb->request_method == "post" and $mybb->input['upsetting'] and $page->active_action == "settings" and $mybb->input['upsetting']['autosavedrafts_engine']) {

		switch ($mybb->input['upsetting']['autosavedrafts_engine']) {

			case 1:
			default:

				$db->drop_table('autosaved_drafts');

				break;

			case 2:

				if (!$db->table_exists('autosaved_drafts')) {
					$collation = $db->build_create_table_collation();
					$db->write_query("CREATE TABLE " . TABLE_PREFIX . "autosaved_drafts (
			            type VARCHAR(20) NOT NULL,
			            uid INT(10) NOT NULL,
			            subject TEXT,
			            message TEXT NOT NULL,
			            dateline INT(15),
			            PRIMARY KEY (type, uid)
			            ) ENGINE=MyISAM{$collation};");
				}

				break;

		}

	}
}
