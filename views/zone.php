<?php
##
## Copyright 2013-2018 Opera Software AS
##
## Licensed under the Apache License, Version 2.0 (the "License");
## you may not use this file except in compliance with the License.
## You may obtain a copy of the License at
##
## http://www.apache.org/licenses/LICENSE-2.0
##
## Unless required by applicable law or agreed to in writing, software
## distributed under the License is distributed on an "AS IS" BASIS,
## WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
## See the License for the specific language governing permissions and
## limitations under the License.
##

try {
	$zone = $zone_dir->get_zone_by_name($router->vars['name'].'.');
} catch(ZoneNotFound $e) {
	require('views/error404.php');
	exit;
}

if(!$active_user->admin && !$active_user->access_to($zone)) {
	require('views/error403.php');
	exit;
}

$deletion = $zone->get_delete_request();
try {
	$rrsets = $zone->list_resource_record_sets();
} catch(ZoneNotFoundInPowerDNS $e) {
	if($active_user->admin) {
		if(isset($_POST['restore_zone'])) {
			$zone->restore();
			redirect();
		}
		$content = new PageSection('zonedeleted');
		$content->set('zone', $zone);
		$content->set('deletion', $deletion);

		$page = new PageSection('base');
		$page->set('title', DNSZoneName::unqualify(punycode_to_utf8($zone->name)));
		$page->set('content', $content);
		$page->set('alerts', $active_user->pop_alerts());

		echo $page->generate();
		exit;
	} else {
		require('views/error404.php');
		exit;
	}
}
$pending = $zone->list_pending_updates();

$changeset_filters = array();
if (isset($_GET['changeset_comment']) and !empty($_GET['changeset_comment'])) {
	$changeset_filters['comment'] = $_GET['changeset_comment'];
}
if (isset($_GET['changeset_start']) and !empty($_GET['changeset_start'])) {
	$start_date = DateTime::createFromFormat("!Y-m-d", $_GET['changeset_start']);
	if ($start_date) {
		$changeset_filters['start_date'] = $start_date;
	} else {
		// warn the user that their date is invalid
		$alert = new UserAlert;
		$alert->content = 'Invalid date supplied; ignoring.';
		$alert->class = 'warning';
		$active_user->add_alert($alert);
	}
}
if (isset($_GET['changeset_end']) and !empty($_GET['changeset_end'])) {
	$end_date = DateTime::createFromFormat("!Y-m-d", $_GET['changeset_end']);
	if ($end_date) {
		$changeset_filters['end_date'] = $end_date;
	} else {
		// warn the user that their date is invalid
		$alert = new UserAlert;
		$alert->content = 'Invalid date supplied; ignoring.';
		$alert->class = 'warning';
		$active_user->add_alert($alert);
	}
}
$changeset_filters['page'] = 1;
if (isset($_GET['page']) and !empty($_GET['page'])) {
	$changeset_filters['page'] = $changeset_filters['page'] = max(1, intval($_GET['page']));
}
list($changeset_pagecount, $changesets) = $zone->list_changesets($changeset_filters);

$access = $zone->list_access();
$cryptokeys = $zone->get_cryptokeys();
$accounts = $zone_dir->list_accounts();
$allusers = $user_dir->list_users();
$replication_types = $replication_type_dir->list_replication_types();
$force_change_review = isset($config['web']['force_change_review']) ? intval($config['web']['force_change_review']) : 0;
$force_change_comment = isset($config['web']['force_change_comment']) ? intval($config['web']['force_change_comment']) : 0;
$account_whitelist = !empty($config['dns']['classification_whitelist']) ? explode(',', $config['dns']['classification_whitelist']) : [];
$force_account_whitelist = !empty($config['dns']['classification_whitelist']) ? 1 : 0;
$dnssec_enabled = isset($config['dns']['dnssec']) ? intval($config['dns']['dnssec']) : 0;
$dnssec_edit = isset($config['dns']['dnssec_edit']) ? intval($config['dns']['dnssec_edit']) : 1;

if($_SERVER['REQUEST_METHOD'] == 'POST') {
	if(isset($_POST['update_rrs'])) {
		$json = new StdClass;
		$json->actions = array();
		if(isset($_POST['comment'])) {
			$json->comment = $_POST['comment'];
		}
		foreach($_POST['updates'] as $update) {
			$json->actions[] = json_decode($update);
		}
		if(($active_user->admin || $active_user->access_to($zone) == 'administrator') && !$force_change_review) {
			try {
				$zone->process_bulk_json_rrset_update(json_encode($json));
				redirect();
			} catch(ResourceRecordInvalid $e) {
				$content = new PageSection('zone_update_failed');
				$message = $e->getMessage();
				if($message == "Key 'priority' not an Integer or not present") {
					$message = 'Wrong JSON API protocol version. Upgrade PowerDNS to >= 3.4.2';
				}
				$content->set('message', $message);
			} catch(RuntimeException $e) {
				$content = new PageSection('zone_update_failed');
				$content->set('message', $e->getMessage());
			}
		} else {
			$zone->add_pending_update(json_encode($json));
			$mail = new Email;
			// Mail SOA contact and administrators about pending update
			$mail->add_recipient(preg_replace('/^([^\.]+)\./', '$1@', trim($zone->soa->contact, '.')));
			foreach($zone->list_access() as $access) {
				if($access->level == 'administrator') {
					$mail->add_recipient($access->user->email, $access->user->name);
				}
			}
			$mail->add_reply_to($active_user->email, $active_user->name);
			$mail->subject = "DNS change requested for ".punycode_to_utf8(DNSZoneName::unqualify($zone->name))." zone by {$active_user->name}";
			$mail->body = "{$active_user->name} ({$active_user->uid}) has requested a change to the ".punycode_to_utf8(DNSZoneName::unqualify($zone->name))." zone.\n\n";
			$mail->body .= "See the changes here:\n\n  {$config['web']['baseurl']}/zones/".urlencode(DNSZoneName::unqualify($zone->name))."#pending";
			$mail->send();
			$alert = new UserAlert;
			$alert->content = "Your changes have been requested and are awaiting approval.";
			$active_user->add_alert($alert);
			redirect('/zones/'.urlencode(DNSZoneName::unqualify($zone->name)).'#pending');
		}
	} elseif(isset($_POST['cancel_update'])) {
		try {
			$update = $zone->get_pending_update_by_id($_POST['cancel_update']);
		} catch(PendingUpdateNotFound $e) {
			$alert = new UserAlert;
			$alert->content = "This update has already been processed.";
			$alert->class = "warning";
			$active_user->add_alert($alert);
		}
		if($update->author->id == $active_user->id) {
			$zone->delete_pending_update($update);
		}
		redirect();
	} elseif(isset($_POST['approve_update']) && ($active_user->admin || $active_user->access_to($zone) == 'administrator')) {
		try {
			$update = $zone->get_pending_update_by_id($_POST['approve_update']);
		} catch(PendingUpdateNotFound $e) {
			$alert = new UserAlert;
			$alert->content = "This update has already been processed.";
			$alert->class = "warning";
			$active_user->add_alert($alert);
			redirect();
		}
		try {
			$zone->process_bulk_json_rrset_update($update->raw_data, $update->author);
			$zone->delete_pending_update($update);
			$mail = new Email;
			$mail->add_recipient($update->author->email, $update->author->name);
			$mail->add_reply_to($active_user->email, $active_user->name);
			$mail->subject = "Approved: DNS change request #{$update->id} for ".punycode_to_utf8($zone->name)." zone";
			$mail->body = "Your change request #{$update->id} for the ".punycode_to_utf8($zone->name)." zone was approved.";
			$mail->send();
			redirect();
		} catch(ResourceRecordInvalid $e) {
			$content = new PageSection('zone_update_failed');
			$message = $e->getMessage();
			if($message == "Key 'priority' not an Integer or not present") {
				$message = 'Wrong JSON API protocol version. Upgrade PowerDNS to >= 3.4.2';
			}
			$content->set('message', $message);
		} catch(RuntimeException $e) {
			$content = new PageSection('zone_update_failed');
			$content->set('message', $e->getMessage());
		}
	} elseif(isset($_POST['reject_update']) && ($active_user->admin || $active_user->access_to($zone) == 'administrator')) {
		try {
			$update = $zone->get_pending_update_by_id($_POST['reject_update']);
		} catch(PendingUpdateNotFound $e) {
			$alert = new UserAlert;
			$alert->content = "This update has already been processed.";
			$alert->class = "warning";
			$active_user->add_alert($alert);
			redirect();
		}
		$mail = new Email;
		$mail->add_recipient($update->author->email, $update->author->name);
		$mail->add_reply_to($active_user->email, $active_user->name);
		$mail->subject = "Rejected: DNS change request #{$update->id} for ".punycode_to_utf8($zone->name)." zone";
		$mail->body = "Your change request #{$update->id} for the ".punycode_to_utf8($zone->name)." zone was rejected.";
		if($_POST['reject_reason']) {
			$mail->body .= " The following reason was given:\n  $_POST[reject_reason]";
		}
		$mail->send();
		$zone->delete_pending_update($update);
		$alert = new UserAlert;
		$alert->content = "Change request rejected.";
		$active_user->add_alert($alert);
		redirect();
	} elseif(isset($_POST['update_zone']) && ($active_user->admin || $active_user->access_to($zone) == 'administrator')) {
		$zone->kind = $_POST['kind'];
		$zone->account = $_POST['classification'];
		$zone->update();
		$primary_ns = $_POST['primary_ns'];
		$contact = $_POST['contact'];
		$refresh = DNSTime::expand($_POST['refresh']);
		$retry = DNSTime::expand($_POST['retry']);
		$expiry = DNSTime::expand($_POST['expire']);
		$default_ttl = DNSTime::expand($_POST['default_ttl']);
		$soa_ttl = DNSTime::expand($_POST['soa_ttl']);
		if($zone->soa->primary_ns != $primary_ns
		|| $zone->soa->contact != $contact
		|| $zone->soa->refresh != $refresh
		|| $zone->soa->retry != $retry
		|| $zone->soa->expiry != $expiry
		|| $zone->soa->default_ttl != $default_ttl
		|| $zone->soa->ttl != $soa_ttl) {
			$record = new StdClass;
			$record->content = "$primary_ns $contact {$zone->soa->serial} $refresh $retry $expiry $default_ttl";
			$record->enabled = 'Yes';
			$update = new StdClass;
			$update->action = 'update';
			$update->oldname = '@';
			$update->oldtype = 'SOA';
			$update->name = '@';
			$update->type = 'SOA';
			$update->ttl = $soa_ttl;
			$update->records = array($record);
			$json = new StdClass;
			$json->actions = array($update);
			$json->comment = $_POST['soa_change_comment'];
			$zone->process_bulk_json_rrset_update(json_encode($json));
		}
		redirect();
	} elseif(isset($_POST['enable_dnssec']) && $active_user->admin && $dnssec_enabled && $dnssec_edit) {
		$zone->dnssec = 1;
		$zone->api_rectify = 1;
		$zone->update();
		redirect();
	} elseif(isset($_POST['enable_api_rectify']) && $active_user->admin && $dnssec_enabled && $dnssec_edit) {
		$zone->api_rectify = 1;
		$zone->update();
		redirect();
	} elseif(isset($_POST['disable_dnssec']) && $active_user->admin && $dnssec_enabled && $dnssec_edit) {
		$zone->dnssec = 0;
		$zone->update();
		redirect();
	} elseif(isset($_POST['request_delete_zone']) && $active_user->admin) {
		$zone->add_delete_request();
		$mail = new Email;
		// Mail SOA contact about deletion request
		$mail->add_recipient(preg_replace('/^([^\.]+)\./', '$1@', trim($zone->soa->contact, '.')));
		$mail->add_reply_to($active_user->email, $active_user->name);
		$mail->subject = "DNS zone deletion request for ".punycode_to_utf8(DNSZoneName::unqualify($zone->name))." zone by {$active_user->name}";
		$mail->body = "{$active_user->name} ({$active_user->uid}) has requested the deletion of the ".punycode_to_utf8(DNSZoneName::unqualify($zone->name))." zone.\n\n";
		$mail->body .= "Approve or reject the change here:\n\n  {$config['web']['baseurl']}/zones/".urlencode(DNSZoneName::unqualify($zone->name))."#tools";
		$mail->send();
		$alert = new UserAlert;
		$alert->content = "The zone deletion has been requested and is awaiting approval.";
		$active_user->add_alert($alert);
		redirect();
	} elseif(isset($_POST['cancel_delete_zone']) && $active_user->admin) {
		$zone->cancel_delete_request();
		redirect();
	} elseif(isset($_POST['confirm_delete_zone']) && $active_user->admin) {
		$zone->confirm_delete_request();
		$alert = new UserAlert;
		$alert->content = "Zone deleted.";
		$active_user->add_alert($alert);
		redirect();
	} elseif(isset($_POST['remove_delete_record']) && $active_user->admin) {
		$zone->remove_delete_record();
		redirect();
	} elseif(isset($_POST['add_access']) && $active_user->admin) {
		try {
			$zoneaccess = new ZoneAccess;
			$zoneaccess->user = $user_dir->get_user_by_uid($_POST['uid']);
			$zoneaccess->level = $_POST['level'];
			$zone->add_access($zoneaccess);
			$alert = new UserAlert;
			$alert->content = "Access added.";
			$active_user->add_alert($alert);
			redirect();
		} catch(UserNotFoundException $e) {
			$content = new PageSection('user_not_found');
			$content->set('uid', $_POST['uid']);
		}
	} elseif(isset($_POST['delete_access']) && $active_user->admin) {
		try {
			$user = $user_dir->get_user_by_uid($_POST['delete_access']);
			$zone->delete_access($user);
			$alert = new UserAlert;
			$alert->content = "Access removed.";
			$active_user->add_alert($alert);
			redirect();
		} catch(UserNotFoundException $e) {
			$content = new PageSection('user_not_found');
			$content->set('uid', $_POST['delete_access']);
		}
	}
}

$local_zone_suffixes = explode(' ', $config['dns']['local_zone_suffixes']);
$local_zone = false;
foreach($local_zone_suffixes as $suffix) {
	$suffix = rtrim($suffix, '.').'.';
	if(substr($zone->name, 0 - strlen($suffix)) == $suffix) $local_zone = true;
}

if(!isset($content)) {
	$content = new PageSection('zone');
	$content->set('zone', $zone);
	$content->set('rrsets', $rrsets);
	$content->set('pending', $pending);
	$content->set('changesets', $changesets);
	$content->set('changeset_filters', $changeset_filters);
	$content->set('changeset_pagecount', $changeset_pagecount);
	$content->set('access', $access);
	$content->set('accounts', $accounts);
	$content->set('cryptokeys', $cryptokeys);
	$content->set('allusers', $allusers);
	$content->set('replication_types', $replication_types);
	$content->set('local_zone', $local_zone);
	$content->set('local_ipv4_ranges', $config['dns']['local_ipv4_ranges']);
	$content->set('local_ipv6_ranges', $config['dns']['local_ipv6_ranges']);
	$content->set('soa_templates', $template_dir->list_soa_templates());
	$content->set('dnssec_enabled', $dnssec_enabled);
	$content->set('dnssec_edit', $dnssec_edit);
	$content->set('deletion', $deletion);
	$content->set('force_change_review', $force_change_review);
	$content->set('force_change_comment', $force_change_comment);
	$content->set('account_whitelist', $account_whitelist);
	$content->set('force_account_whitelist', $force_account_whitelist);
}

$page = new PageSection('base');
$page->set('title', DNSZoneName::unqualify(punycode_to_utf8($zone->name)));
$page->set('content', $content);
$page->set('alerts', $active_user->pop_alerts());

echo $page->generate();
