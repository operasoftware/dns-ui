<?php
##
## Copyright 2013-2017 Opera Software AS
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

if(!$active_user->admin) {
	require('views/error403.php');
	exit;
}

$rrsets = $zone->list_resource_record_sets();

if($_SERVER['REQUEST_METHOD'] == 'POST') {
	if(isset($_POST['suffix'])) {
		$newzonename = utf8_to_punycode($_POST['suffix']).'.'.$zone->name;
		$split = array();
		$cname_error = false;
		foreach($rrsets as $rrset) {
			if((stripos($rrset->name, '.'.$newzonename) === strlen($rrset->name) - strlen('.'.$newzonename)
				|| $rrset->name == $newzonename) && $rrset->type != 'SOA' && $rrset->type != 'NS') {
				if($rrset->name == $newzonename && $rrset->type == 'CNAME') {
					$cname_error = true;
					$alert = new UserAlert;
					$alert->content = "It is not possible to have a CNAME record at the root of the zone. You will need to change the highlighted CNAME record into an A/AAAA record before proceeding.";
					$alert->class = "danger";
					$active_user->add_alert($alert);
				}
				$split[] = $rrset;
			}
		}
		if(isset($_POST['confirm']) && count($split) > 0 && !$cname_error) {
			// Build new zone with split records
			// Copy nameservers and SOA from old zone
			$newzone = new Zone;
			$newzone->name = $newzonename;
			$newzone->account = $zone->account;
			$newzone->kind = 'Master';
			$newzone->nameservers = $zone->nameservers;
			foreach($split as $rrset) {
				$newzone->add_resource_record_set($rrset);
			}
			$soa = new ResourceRecord;
			$soa->content = $zone->soa->content;
			$soa->disabled = false;
			$soaset = new ResourceRecordSet;
			$soaset->name = $newzonename;
			$soaset->type = 'SOA';
			$soaset->ttl = $zone->soa->ttl;
			$soaset->add_resource_record($soa);
			$newzone->add_resource_record_set($soaset);
			try {
				// Create new zone
				$zone_dir->create_zone($newzone);
				// Update old zone (remove split records)
				$changes = array();
				foreach($split as $rrset) {
					$change = new Change;
					$change->before = serialize($rrset);
					$changes[] = $change;
					$zone->delete_resource_record_set($rrset);
				}
				$zone->commit_changes();
				$changeset = new ChangeSet;
				if(!empty($_POST['comment'])) {
					$changeset->comment = $_POST['comment'];
				}
				$zone->add_changeset($changeset);
				foreach($changes as $change) {
					$changeset->add_change($change);
				}
				// Create git commit for this change
				$git_commit_comment = "Zone {$newzonename} split off from {$zone->name} via DNS UI\n";
				$git_commit_comment .= "\n{$config['web']['baseurl']}/zones/".urlencode($zone->name).'#changelog#'.$changeset->id;
				if(!empty($_POST['comment'])) {
					$git_commit_comment .= "\nChange comment: {$_POST['comment']}";
				}
				$zone_dir->git_tracked_export(array($zone), $git_commit_comment);
				$alert = new UserAlert;
				$alert->content = "Zone split of ".DNSZoneName::unqualify($newzonename)." from ".DNSZoneName::unqualify($zone->name)." has been completed.";
				$active_user->add_alert($alert);
				$content = new PageSection('zonesplitcompleted');
				$content->set('zone', $zone);
				$content->set('newzonename', $newzonename);
			} catch(Pest_InvalidRecord $e) {
				$content = new PageSection('zone_add_failed');
				$content->set('message', json_decode($e->getMessage())->error);
			}
		} else {
			$content = new PageSection('zonesplit');
			$content->set('zone', $zone);
			$content->set('newzonename', $newzonename);
			$content->set('suffix', $_POST['suffix']);
			$content->set('split', $split);
			$content->set('cname_error', $cname_error);
		}
	}
}

$page = new PageSection('base');
$page->set('title', 'Split preview for '.$zone->name);
$page->set('content', $content);
$page->set('alerts', $active_user->list_alerts());

echo $page->generate();

