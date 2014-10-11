<?php

function webpages_content(&$a) {

	if(argc() > 1)
		$which = argv(1);
	else {
		notice( t('Requested profile is not available.') . EOL );
		$a->error = 404;
		return;
	}

	$profile = 0;
	$channel = $a->get_channel();

	if((local_user()) && (argc() > 2) && (argv(2) === 'view')) {
		$which = $channel['channel_address'];
		$profile = argv(1);
	}

	profile_load($a,$which,$profile);


	// Figure out who the page owner is.
	$r = q("select channel_id from channel where channel_address = '%s'",
		dbesc($which)
	);
	if($r) {
		$owner = intval($r[0]['channel_id']);
	}

	// Get the observer, check their permissions

	$observer = $a->get_observer();
	$ob_hash = (($observer) ? $observer['xchan_hash'] : '');

	$perms = get_all_perms($owner,$ob_hash);

	if(! $perms['write_pages']) {
		notice( t('Permission denied.') . EOL);
		return;
	}

//		if(local_user() && local_user() == $owner) {
//			$a->set_widget('design',design_tools());
//		}


	if(feature_enabled($owner,'expert')) {
		$mimetype = (($_REQUEST['mimetype']) ? $_REQUEST['mimetype'] : get_pconfig($owner,'system','page_mimetype'));
		if(! $mimetype)
			$mimetype = 'choose';	
	}
	else {
		$mimetype = 'text/bbcode';
	}

	$layout = (($_REQUEST['layout']) ? $_REQUEST['layout'] : get_pconfig($owner,'system','page_layout'));
	if(! $layout)
		$layout = 'choose';


// Create a status editor (for now - we'll need a WYSIWYG eventually) to create pages
// Nickname is set to the observers xchan, and profile_uid to the owners.  This lets you post pages at other people's channels.
	require_once ('include/conversation.php');
	require_once('include/acl_selectors.php');


	if(local_user() && local_user() == $a->profile_uid) {
		$channel = $a->get_channel();
		$channel_acl = array(
			'allow_cid' => $channel['channel_allow_cid'],
			'allow_gid' => $channel['channel_allow_gid'],
			'deny_cid'  => $channel['channel_deny_cid'],
			'deny_gid'  => $channel['channel_deny_gid']
		);
	}
	else
		$channel_acl = array();

	require_once('include/conversation.php');
	$o = profile_tabs($a,true);


	$x = array(
		'webpage' => ITEM_WEBPAGE,
		'is_owner' => true,
		'nickname' => $a->profile['channel_address'],
		'lockstate' => (($group || $cid || $channel['channel_allow_cid'] || $channel['channel_allow_gid'] || $channel['channel_deny_cid'] || $channel['channel_deny_gid']) ? 'lock' : 'unlock'),
		'bang' => (($group || $cid) ? '!' : ''),
		'acl' => ((local_user() && local_user() == $owner) ? populate_acl($channel_acl,false) : ''),
		'visitor' => true,
		'profile_uid' => intval($owner),
		'mimetype' => $mimetype,
		'layout' => $layout,
	);
	
	if($_REQUEST['title'])
		$x['title'] = $_REQUEST['title'];
	if($_REQUEST['body'])
		$x['body'] = $_REQUEST['body'];
	if($_REQUEST['pagetitle'])
		$x['pagetitle'] = $_REQUEST['pagetitle'];

	$o .= status_editor($a,$x);

	// Get a list of webpages.  We can't display all them because endless scroll makes that unusable, so just list titles and an edit link.
	//TODO - this should be replaced with pagelist_widget

	$r = q("select * from item_id left join item on item_id.iid = item.id where item_id.uid = %d and service = 'WEBPAGE' order by item.created desc",
		intval($owner)
	);

	$pages = null;

	if($r) {
		$pages = array();
		foreach($r as $rr) {
			unobscure($rr);
			$pages[$rr['iid']][] = array('url' => $rr['iid'],'pagetitle' => $rr['sid'],'title' => $rr['title'],'created' => datetime_convert('UTC',date_default_timezone_get(),$rr['created']),'edited' => datetime_convert('UTC',date_default_timezone_get(),$rr['edited']));
		}
	}


//Build the base URL for edit links
		$url = z_root() . "/editwebpage/" . $which;
// This isn't pretty, but it works.  Until I figure out what to do with the UI, it's Good Enough(TM).
	return $o . replace_macros(get_markup_template("webpagelist.tpl"), array(
    	'$listtitle = t('Webpages'),
		'$baseurl' => $url,
		'$edit' => t('Edit'),
		'$pages' => $pages,
		'$channel' => $which,
		'$view' => t('View'),
		'$preview' => t('Preview'),
		'$actions_txt' => t('Actions'),
		'$pagelink_txt' => t('Page Link'),
		'$title_txt' => t('Title'),
		'$created_txt' => t('Created'),
		'$edited_txt' => t('Edited')

));

	$o .= '</div>'; 

}
