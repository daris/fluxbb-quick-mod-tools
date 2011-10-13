<?php

define('PUN_ROOT', '../../');
require PUN_ROOT.'include/common.php';

require PUN_ROOT.'lang/'.$pun_user['language'].'/forum.php';
require PUN_ROOT.'lang/'.$pun_user['language'].'/misc.php';
require PUN_ROOT.'lang/'.$pun_user['language'].'/post.php';
require PUN_ROOT.'include/quick_mod_tools/functions.php';

// All other functions require moderator/admin access
$fid = isset($_GET['fid']) ? intval($_GET['fid']) : 0;

$result = $db->query('SELECT f.moderators FROM '.$db->prefix.'forums AS f LEFT JOIN '.$db->prefix.'forum_perms AS fp ON (fp.forum_id=f.id AND fp.group_id='.$pun_user['g_id'].') WHERE (fp.read_forum IS NULL OR fp.read_forum=1) AND f.id='.$fid) or error('Unable to fetch forum info', __FILE__, __LINE__, $db->error());
if (!$db->num_rows($result))
	die($lang_common['Bad request']);

$moderators = $db->result($result);
$mods_array = ($moderators != '') ? unserialize($moderators) : array();
$is_admmod = ($pun_user['g_id'] == PUN_ADMIN || ($pun_user['g_moderator'] == '1' && array_key_exists($pun_user['username'], $mods_array))) ? true : false;

confirm_referrer('viewforum.php');

$topic_id = intval($_GET['tid']);
$action = isset($_GET['action']) ? $_GET['action'] : null;
$reload = false;

// Verify that the topic is valid
$result = $db->query('SELECT p.poster_id'.(isset($_POST['subject']) ? ', p.message' : '').', t.closed FROM '.$db->prefix.'topics AS t LEFT JOIN '.$db->prefix.'posts AS p ON p.id=t.first_post_id WHERE t.id='.$topic_id.' AND t.forum_id='.$fid) or error('Unable to check topics', __FILE__, __LINE__, $db->error());
if (!$db->num_rows($result))
	die($lang_common['Bad request']);
	
$cur_topic = $db->fetch_assoc($result);

if ($action == 'open' || $action == 'close')
{
	if (!$is_admmod)
		die($lang_common['No permission']);

	$closed = ($action == 'open') ? 0 : 1;
	$db->query('UPDATE '.$db->prefix.'topics SET closed='.$closed.' WHERE id='.$topic_id.' AND forum_id='.$fid) or error('Unable to close topic', __FILE__, __LINE__, $db->error());

}
else if ($action == 'stick' || $action == 'unstick')
{
	if (!$is_admmod)
		die($lang_common['No permission']);

	$sticky = ($action == 'stick') ? 1 : 0;
	$reload = true;
	
	$db->query('UPDATE '.$db->prefix.'topics SET sticky='.$sticky.' WHERE id='.$topic_id.' AND forum_id='.$fid) or error('Unable to close topic', __FILE__, __LINE__, $db->error());
}

elseif (isset($_POST['subject']))
{
	if (!$is_admmod && ($cur_topic['poster_id'] != $pun_user['id'] || $pun_user['g_edit_posts'] == '0' || $cur_topic['closed'] == '1'))
		die($lang_common['No permission']);
		
	require PUN_ROOT.'include/search_idx.php';
	$subject = $_POST['subject'];
	$message = $_POST['message'];
	if (empty($subject))
		die($lang_post['No subject']);

	$db->query('UPDATE '.$db->prefix.'topics SET subject=\''.$db->escape($subject).'\' WHERE id='.$topic_id.' AND forum_id='.$fid) or error('Unable to update topic subject', __FILE__, __LINE__, $db->error());

	// We changed the subject, so we need to take that into account when we update the search words
	update_search_index('edit', $topic_id, $message, $subject);
}

elseif ($action == 'delete')
{
	if (!$is_admmod && ($cur_topic['poster_id'] != $pun_user['id'] || $pun_user['g_delete_topics'] == '0' || $cur_topic['closed'] == '1'))
		die($lang_common['No permission']);

	require PUN_ROOT.'include/search_idx.php';
	delete_topic($topic_id);

	update_forum($fid);
	
	$reload = true;
}

if ($reload)
{
	$db->close();
	die('reload');
}

$id = $fid;
require PUN_ROOT.'include/quick_mod_tools/topic.php';