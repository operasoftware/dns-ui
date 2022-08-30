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

if(isset($router->vars['objects'])) {
	$api = new API;
	switch($router->vars['objects']) {
	case 'zones':
		if(isset($router->vars['id'])) {
			if(isset($router->vars['subobjects'])) {
				switch($router->vars['subobjects']) {
				case 'changes':
					if(isset($router->vars['subid'])) {
						$api->zone_change($router->vars['id'], $router->vars['subid']);
					} else {
						$api->zone_changes($router->vars['id']);
					}
				}
			} else {
				$api->zone($router->vars['id']);
			}
		} else {
			$api->zones();
		}
	default:
		header('HTTP/1.1 404 Not found');
	}
} else {
	$content = new PageSection('apihelp');

	$page = new PageSection('base');
	$page->set('title', 'API documentation');
	$page->set('content', $content);
	$page->set('alerts', array());
	echo $page->generate();
}

class API {
	public function options($options, $params = array()) {
		if($_SERVER['REQUEST_METHOD'] == 'OPTIONS' || !isset($options[$_SERVER['REQUEST_METHOD']])) {
			if(!isset($options[$_SERVER['REQUEST_METHOD']])) {
				header('HTTP/1.1 405 Method not allowed');
			}
			$optionlist = array_keys($options);
			$optionlist[] = 'OPTIONS';
			header('Allow: '.implode(', ', $optionlist));
			exit;
		} else {
			try {
				call_user_func_array(array($this, $options[$_SERVER['REQUEST_METHOD']]), $params);
			} catch(AccessDenied $e) {
				header('HTTP/1.1 403 Forbidden');
				log_exception($e);
				$this->output(['errors' => [['userMessage' => 'Access denied', 'code' => 3]]]);
			} catch(InvalidJSON $e) {
				header('HTTP/1.1 400 Bad request');
				log_exception($e);
				$this->output(['errors' => [['userMessage' => 'Invalid JSON', 'internalMessage' => $e->getMessage(), 'code' => 1]]]);
			} catch(BadData $e) {
				header('HTTP/1.1 400 Bad request');
				log_exception($e);
				$this->output(['errors' => [['userMessage' => 'Bad data', 'internalMessage' => $e->getMessage(), 'code' => 2]]]);
			} catch(ResourceRecordInvalid $e) {
				header('HTTP/1.1 400 Bad request');
				log_exception($e);
				$this->output(['errors' => [['userMessage' => 'Invalid resource record', 'internalMessage' => $e->getMessage(), 'code' => 4]]]);
			} catch(Exception $e) {
				header('HTTP/1.1 500 Internal server error');
				log_exception($e);
				$this->output(['errors' => [['userMessage' => 'Something went wrong (server fault)', 'code' => 0]]]);
			}
		}
	}

	public function zones() {
		$this->options(array('GET' => 'list_zones'));
	}

	public function list_zones() {
		global $active_user;
		$zones = $active_user->list_accessible_zones();
		$list = array();
		foreach($zones as $zone) {
			$item = new StdClass;
			$item->name = $zone->name;
			$item->serial = $zone->serial;
			$list[] = $item;
		}
		$this->output($list);
	}

	public function zone($zone_name) {
		$this->options(array('GET' => 'show_zone', 'PATCH' => 'update_zone_rrsets'), array($zone_name));
	}

	public function show_zone($zone_name) {
		global $zone_dir, $active_user;
		$zone = $zone_dir->get_zone_by_name($zone_name);
		if(!$active_user->admin && !$active_user->access_to($zone)) throw new AccessDenied;
		$data = new StdClass;
		$data->name = $zone->name;
		$data->serial = $zone->serial;
		$data->rrsets = array();
		foreach($zone->list_resource_record_sets() as $rrset) {
			$rrs_data = new StdClass;
			$rrs_data->name = DNSName::abbreviate($rrset->name, $zone->name);
			$rrs_data->type = $rrset->type;
			$rrs_data->ttl = DNSTime::abbreviate($rrset->ttl);
			$rrs_data->records = array();
			foreach($rrset->list_resource_records() as $rr) {
				$rr_data = new StdClass;
				$rr_data->content = DNSContent::decode($rr->content, $rrset->type, $zone_name);
				$rr_data->enabled = !$rr->disabled;
				$rrs_data->records[] = $rr_data;
			}
			$rrs_data->comments = array();
			foreach($rrset->list_comments() as $comment) {
				$comment_data = new StdClass;
				$comment_data->content = $comment->content;
				$comment_data->account = $comment->account;
				$comment_data->modified_at = $comment->modified_at;
				$rrs_data->comments[] = $comment_data;
			}
			$data->rrsets[] = $rrs_data;
		}
		$this->output($data);
	}

	public function update_zone_rrsets($zone_name) {
		global $zone_dir, $active_user;
		$zone = $zone_dir->get_zone_by_name($zone_name);
		if(!$active_user->admin && !$active_user->access_to($zone)) throw new AccessDenied;
		$json = file_get_contents('php://input');
		$zone->process_bulk_json_rrset_update($json);
		$this->output(null);
	}

	public function zone_changes($zone_name) {
		$this->options(array('GET' => 'list_zone_changes'), array($zone_name));
	}

	public function list_zone_changes($zone_name) {
		global $zone_dir, $active_user;
		$zone = $zone_dir->get_zone_by_name($zone_name);
		if(!$active_user->admin && !$active_user->access_to($zone)) throw new AccessDenied;
		list($changeset_pagecount, $changesets) = $zone->list_changesets();
		$list = array();
		foreach($changesets as $changeset) {
			$item = new StdClass;
			$item->id = $changeset->id;
			$item->author_uid = $changeset->author->uid;
			$item->change_date = $changeset->change_date->format('c');
			$item->comment = $changeset->comment;
			$item->deleted = $changeset->deleted;
			$item->added = $changeset->added;
			$list[] = $item;
		}
		$this->output($list);
	}

	public function zone_change($zone_name, $changeset_id) {
		$this->options(array('GET' => 'show_zone_change'), array($zone_name, $changeset_id));
	}

	public function show_zone_change($zone_name, $changeset_id) {
		global $zone_dir, $active_user;
		$zone = $zone_dir->get_zone_by_name($zone_name);
		if(!$active_user->admin && !$active_user->access_to($zone)) throw new AccessDenied;
		$changeset = $zone->get_changeset_by_id($changeset_id);
		$data = new StdClass;
		$data->id = $changeset->id;
		$data->author_uid = $changeset->author->uid;
		$data->change_date = $changeset->change_date->format('c');
		$data->comment = $changeset->comment;
		$data->deleted = $changeset->deleted;
		$data->added = $changeset->added;
		$data->changes = array();
		foreach($changeset->list_changes() as $change) {
			$c_data = new StdClass;
			$states = array();
			if(!is_null($change->before)) {
				$states['before'] = unserialize($change->before);
			}
			if(!is_null($change->after)) {
				$states['after'] = unserialize($change->after);
			}
			foreach($states as $state => $rrset) {
				if($rrset) {
					$c_data->{$state} = new StdClass;
					$c_data->{$state}->name = $rrset->name;
					$c_data->{$state}->type = $rrset->type;
					$c_data->{$state}->ttl = DNSTime::abbreviate($rrset->ttl);
					$c_data->{$state}->rrs = array();
					$c_data->{$state}->comment = $rrset->merge_comment_text();
					$rrs = $rrset->list_resource_records();
					foreach($rrs as $rr) {
						$rr_data = new StdClass;
						$rr_data->content = DNSContent::decode($rr->content, $rr->type, $zone_name);
						$rr_data->enabled = !$rr->disabled;
						$c_data->{$state}->rrs[] = $rr_data;
					}
				}
			}
			$data->changes[] = $c_data;
		}
		$this->output($data);
	}

	public function created($url) {
		header('HTTP/1.1 201 Created');
		header('Location: /api/v2'.$url);
		exit;
	}

	private function output($data) {
		header('Content-type: application/json');
		echo json_encode($data, JSON_PRETTY_PRINT);
		exit;
	}
}
