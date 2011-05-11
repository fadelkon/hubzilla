<?php

require_once('Scrape.php');

function follow_post(&$a) {

	if(! local_user()) {
		notice( t('Permission denied.') . EOL);
		goaway($_SESSION['return_url']);
		// NOTREACHED
	}

	$url = $orig_url = notags(trim($_POST['url']));

	if(! allowed_url($url)) {
		notice( t('Disallowed profile URL.') . EOL);
		goaway($_SESSION['return_url']);
		// NOTREACHED
	}


	$ret = probe_url($url);


	if($ret['network'] === NETWORK_DFRN) {
		if(strlen($a->path))
			$myaddr = bin2hex($a->get_baseurl() . '/profile/' . $a->user['nickname']);
		else
			$myaddr = bin2hex($a->user['nickname'] . '@' . $a->get_hostname());
 
		goaway($ret['request'] . "&addr=$myaddr");
		
		// NOTREACHED
	}

	// do we have enough information?
	
	if(! ((x($ret,'name')) && (x($ret,'poll')) && ((x($ret,'url')) || (x($ret,'addr'))))) {
		notice( t('The profile address specified does not provide adequate information.') . EOL);
		goaway($_SESSION['return_url']);
	}


	if(! $ret['notify']) {
		notice( t('Limited profile. This person will be unable to receive direct/personal notifications from you.') . EOL);
	}

	$writeable = ((($ret['network'] === NETWORK_OSTATUS) && ($ret['notify'])) ? 1 : 0);
	if($ret['network'] === NETWORK_MAIL) {
		$writeable = 1;
		
	}
	// check if we already have a contact
	// the poll url is more reliable than the profile url, as we may have
	// indirect links or webfinger links

	$r = q("SELECT * FROM `contact` WHERE `uid` = %d AND `poll` = '%s' LIMIT 1",
		intval(local_user()),
		dbesc($ret['poll'])
	);			

	if(count($r)) {
		// update contact
		if($r[0]['rel'] == REL_VIP) {
			q("UPDATE `contact` SET `rel` = %d , `readonly` = 0 WHERE `id` = %d AND `uid` = %d LIMIT 1",
				intval(REL_BUD),
				intval($r[0]['id']),
				intval(local_user())
			);
		}
	}
	else {
		// create contact record 
		$r = q("INSERT INTO `contact` ( `uid`, `created`, `url`, `addr`, `alias`, `notify`, `poll`, `name`, `nick`, `photo`, `network`, `rel`, `priority`,
			`writable`, `blocked`, `readonly`, `pending` )
			VALUES ( %d, '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', %d, %d, %d, 0, 0, 0 ) ",
			intval(local_user()),
			dbesc(datetime_convert()),
			dbesc($ret['url']),
			dbesc($ret['addr']),
			dbesc($ret['alias']),
			dbesc($ret['notify']),
			dbesc($ret['poll']),
			dbesc($ret['name']),
			dbesc($ret['nick']),
			dbesc($ret['photo']),
			dbesc($ret['network']),
			intval(($ret['network'] === NETWORK_MAIL) ? REL_BUD : REL_FAN),
			intval($ret['priority']),
			intval($writeable)
		);
	}

	$r = q("SELECT * FROM `contact` WHERE `url` = '%s' AND `uid` = %d LIMIT 1",
		dbesc($ret['url']),
		intval(local_user())
	);

	if(! count($r)) {
		notice( t('Unable to retrieve contact information.') . EOL);
		goaway($_SESSION['return_url']);
		// NOTREACHED
	}

	$contact = $r[0];
	$contact_id  = $r[0]['id'];

	require_once("Photo.php");

	$photos = import_profile_photo($ret['photo'],local_user(),$contact_id);

	$r = q("UPDATE `contact` SET `photo` = '%s', 
			`thumb` = '%s',
			`micro` = '%s', 
			`name-date` = '%s', 
			`uri-date` = '%s', 
			`avatar-date` = '%s'
			WHERE `id` = %d LIMIT 1
		",
			dbesc($photos[0]),
			dbesc($photos[1]),
			dbesc($photos[2]),
			dbesc(datetime_convert()),
			dbesc(datetime_convert()),
			dbesc(datetime_convert()),
			intval($contact_id)
		);			


	// pull feed and consume it, which should subscribe to the hub.

	proc_run('php',"include/poller.php","$contact_id");

	// create a follow slap

	$tpl = get_markup_template('follow_slap.tpl');
	$slap = replace_macros($tpl, array(
		'$name' => $a->user['username'],
		'$profile_page' => $a->get_baseurl() . '/profile/' . $a->user['nickname'],
		'$photo' => $a->contact['photo'],
		'$thumb' => $a->contact['thumb'],
		'$published' => datetime_convert('UTC','UTC', 'now', ATOM_TIME),
		'$item_id' => 'urn:X-dfrn:' . $a->get_hostname() . ':follow:' . random_string(),
		'$title' => '',
		'$type' => 'text',
		'$content' => t('following'),
		'$nick' => $a->user['nickname'],
		'$verb' => ACTIVITY_FOLLOW,
		'$ostat_follow' => ''
	));

	$r = q("SELECT `contact`.*, `user`.* FROM `contact` LEFT JOIN `user` ON `contact`.`uid` = `user`.`uid` 
			WHERE `user`.`uid` = %d AND `contact`.`self` = 1 LIMIT 1",
			intval(local_user())
	);


	if((count($r)) && (x($contact,'notify')) && (strlen($contact['notify']))) {
		require_once('include/salmon.php');
		slapper($r[0],$contact['notify'],$slap);
	}

	goaway($_SESSION['return_url']);
	// NOTREACHED
}
