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

if(!$active_user->admin && !$active_user->access_to($zone)) {
	require('views/error403.php');
	exit;
}

if(isset($_FILES['zonefile'])) {
	$lines = file_get_contents($_FILES['zonefile']['tmp_name']);
	$zonefile = new BindZonefile($lines);
	try {
		$new_rrsets = $zonefile->parse_into_rrsets($zone, $_POST['comment_handling']);
		$modifications = merge_rrsets($zone, $new_rrsets);
	} catch(ZoneImportError $e) {
		$content = new PageSection('zone_update_failed');
		$content->set('message', $e->getMessage());
	}
	if(!isset($content)) {
		$content = new PageSection('zoneimport');
		$content->set('zone', $zone);
		$content->set('modifications', $modifications);
	}
} else {
	redirect('/zones/'.urlencode($zone->name));
}

$page = new PageSection('base');
$page->set('title', 'Import preview for '.DNSZoneName::unqualify(punycode_to_utf8($zone->name)).' zone update');
$page->set('content', $content);
$page->set('alerts', $active_user->pop_alerts());

echo $page->generate();

function merge_rrsets($zone, $new_rrsets) {
	global $active_user;
	// Compare existing content with new content and collate the differences
	$rrsets = $zone->list_resource_record_sets();
	$old_rrsets = $rrsets;
	$modifications = array('add' => array(), 'update' => array(), 'delete' => array());
	foreach($new_rrsets as $ref => $new_rrset) {
		if($new_rrset->type == 'SOA') continue;
		if(isset($old_rrsets[$ref])) {
			$old_rrset = $old_rrsets[$ref];
			$old_rrs = $old_rrset->list_resource_records();
			$new_rrs = $new_rrset->list_resource_records();
			$old_comment = $old_rrset->merge_comment_text();
			$new_comment = $new_rrset->merge_comment_text();
			$rrset_modifications = array();
			if($old_rrset->ttl != $new_rrset->ttl) {
				$rrset_modifications[] = 'TTL changed from '.DNSTime::abbreviate($old_rrset->ttl).' to '.DNSTime::abbreviate($new_rrset->ttl);
			}
			foreach($new_rrs as $new_rr) {
				$rr_match = false;
				foreach($old_rrs as $rr_ref => $old_rr) {
					if($new_rr->content == $old_rr->content) {
						$rr_match = true;
						unset($old_rrs[$rr_ref]);
						break;
					}
				}
				if($rr_match) {
					if($new_rr->disabled && !$old_rr->disabled) {
						$rrset_modifications[] = 'Disabled RR: '.$new_rr->content;
					}
					if(!$new_rr->disabled && $old_rr->disabled) {
						$rrset_modifications[] = 'Enabled RR: '.$new_rr->content;
					}
				} else {
					// New RR
					$rrset_modifications[] = 'New RR: '.$new_rr->content;
				}
			}
			foreach($old_rrs as $old_rr) {
				// Deleted RR
				$rrset_modifications[] = 'Deleted RR: '.$old_rr->content;
			}
			$new_rrset->clear_comments();
			if($old_comment == $new_comment) {
				foreach($old_rrset->list_comments() as $comment) {
					$new_rrset->add_comment($comment);
				}
			} else {
				if($old_comment != '') $rrset_modifications[] = 'Deleted comment: '.$old_comment;
				if($new_comment != '') {
					$rrset_modifications[] = 'New comment: '.$new_comment;
					$comment = new Comment;
					$comment->content = $new_comment;
					$comment->account = $active_user->uid;
					$new_rrset->add_comment($comment);
				}
			}
			if(count($rrset_modifications) > 0) {
				$modifications['update'][$ref] = array();
				$modifications['update'][$ref]['new'] = $new_rrset;
				$modifications['update'][$ref]['changelist'] = $rrset_modifications;
				$modifications['update'][$ref]['json'] = build_json('update', $new_rrset, $zone->name);
			}
		} else {
			// New RRSet
			$modifications['add'][$ref] = array();
			$modifications['add'][$ref]['new'] = $new_rrset;
			$modifications['add'][$ref]['json'] = build_json('add', $new_rrset, $zone->name);
		}
	}
	foreach($old_rrsets as $ref => $old_rrset) {
		if($old_rrset->type == 'SOA') continue;
		if(!isset($new_rrsets[$ref])) {
			// Deleted RRSet
			$modifications['delete'][$ref] = array();
			$modifications['delete'][$ref]['old'] = $old_rrset;
			$modifications['delete'][$ref]['json'] = build_json('delete', $old_rrset, $zone->name);
		}
	}
	return $modifications;
}

function build_json($action, $rrset, $zonename) {
	$data = new StdClass;
	$data->action = $action;
	$data->name = DNSName::abbreviate($rrset->name, $zonename);
	$data->type = $rrset->type;
	$data->ttl = $rrset->ttl;
	if($action != 'add') {
		$data->oldname = $data->name;
		$data->oldtype = $data->type;
	}
	if($action != 'delete') {
		$data->records = array();
		foreach($rrset->list_resource_records() as $rr) {
			$rr_data = new StdClass;
			$rr_data->content = DNSContent::decode($rr->content, $rrset->type, $zonename);
			$rr_data->enabled = $rr->disabled ? 'No' : 'Yes';
			$data->records[] = $rr_data;
		}
		$data->comment = $rrset->merge_comment_text();
	}
	return json_encode($data);
}

