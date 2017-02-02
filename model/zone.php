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

class Zone extends Record {
	protected $table = 'zone';
	private $powerdns;
	private $rrsets = null;
	private $soa = null;
	private $nameservers = null;
	private $changes = array();

	public function __construct($id = null, $preload_data = array()) {
		parent::__construct($id, $preload_data);
		global $powerdns;
		$this->powerdns = $powerdns;
	}

	public function &__get($field) {
		switch($field) {
		case 'soa':
			if(is_null($this->soa)) $this->list_resource_record_sets();
			return $this->soa;
		case 'nameservers':
			if(is_null($this->nameservers)) $this->list_resource_record_sets();
			return $this->nameservers;
		default:
			return parent::__get($field);
		}
	}

	public function __set($field, $value) {
		switch($field) {
		case 'nameservers':
			$this->nameservers = $value;
			break;
		default:
			parent::__set($field, $value);
		}
	}

	public function add_resource_record_set(ResourceRecordSet $rrset) {
		$this->add_or_update_resource_record_set($rrset);
		syslog_report(LOG_INFO, "zone={$this->name};object=rrset;action=add;name={$rrset->name}");
	}

	public function update_resource_record_set(ResourceRecordSet $rrset) {
		$this->add_or_update_resource_record_set($rrset);
		syslog_report(LOG_INFO, "zone={$this->name};object=rrset;action=update;name={$rrset->name}");
	}

	public function add_or_update_resource_record_set(ResourceRecordSet $rrset) {
		$this->rrsets[$rrset->name.' '.$rrset->type] = $rrset;
		$change = new StdClass;
		$change->name = $rrset->name;
		$change->type = $rrset->type;
		$change->ttl = $rrset->ttl;
		$change->changetype = 'REPLACE';
		$change->records = array();
		foreach($rrset->list_resource_records() as $record) {
			$change_record = new StdClass;
			$change_record->name = $rrset->name;
			$change_record->type = $rrset->type;
			$change_record->content = $record->content;
			$change_record->disabled = $record->disabled;
			$change_record->{'set-ptr'} = $record->{'set-ptr'};
			$change->records[] = $change_record;
		}
		$change->comments = array();
		foreach($rrset->list_comments() as $comment) {
			$change_comment = new StdClass;
			$change_comment->content = $comment->content;
			$change_comment->account = $comment->account;
			if($comment->modified_at) {
				$change_comment->modified_at = $comment->modified_at;
			}
			$change->comments[] = $change_comment;
		}
		$this->changes[] = $change;
	}

	public function delete_resource_record_set(ResourceRecordSet $rrset) {
		unset($this->rrsets[$rrset->name.' '.$rrset->type]);
		$change = new StdClass;
		$change->name = $rrset->name;
		$change->type = $rrset->type;
		$change->changetype = 'DELETE';
		$change->records = array();
		$change->comments = array();
		$this->changes[] = $change;
		syslog_report(LOG_INFO, "zone={$this->name};object=rrset;action=delete;name={$rrset->name}");
	}

	public function commit_changes() {
		$patch = new StdClass;
		$patch->rrsets = $this->changes;
		//echo 'PATCH: <pre>'.hesc(json_encode($patch, JSON_PRETTY_PRINT)).'</pre>';die;
		try {
			$this->powerdns->patch('zones/'.urlencode($this->pdns_id), $patch);
		} catch(Pest_InvalidRecord $e) {
			syslog_report(LOG_ERR, "zone={$this->name};object=zone;action=update;status=failed");
			throw new ResourceRecordInvalid(json_decode($e->getMessage())->error);
		}
		$this->send_notify();
		syslog_report(LOG_INFO, "zone={$this->name};object=zone;action=update;status=succeeded");
	}

	public function send_notify() {
		$this->powerdns->put('zones/'.urlencode($this->pdns_id).'/notify', '');
	}

	public function update_account($account) {
		$this->account = $account;
		$update = new StdClass;
		$update->kind = 'master';
		$update->account = $account;
		$this->powerdns->put('zones/'.urlencode($this->pdns_id), $update);
		$stmt = $this->database->prepare('UPDATE zone SET account = ? WHERE id = ?');
		$stmt->bindParam(1, $this->account, PDO::PARAM_STR);
		$stmt->bindParam(2, $this->id, PDO::PARAM_INT);
		$stmt->execute();
	}

	public function &list_resource_record_sets() {
		if(is_null($this->rrsets)) {
			$data = $this->powerdns->get('zones/'.urlencode($this->pdns_id));
			$this->rrsets = array();
			$this->nameservers = array();
			usort($data->rrsets,
				function($a, $b) {
					if($a->type == 'SOA' && $b->type != 'SOA') return -1;
					if($b->type == 'SOA' && $a->type != 'SOA') return 1;
					$aname = implode(',', array_reverse(explode('.', $a->name)));
					$bname = implode(',', array_reverse(explode('.', $b->name)));
					if($aname == $bname) {
						if($a->type == 'NS' && $b->type != 'NS') return -1;
						if($b->type == 'NS' && $a->type != 'NS') return 1;
						return 0;
					} else {
						return strnatcasecmp($aname, $bname);
					}
				}
			);
			foreach($data->rrsets as $recordset) {
				$rrset = new ResourceRecordSet;
				$rrset->name = $recordset->name;
				$rrset->type = $recordset->type;
				$rrset->ttl = $recordset->ttl;
				$this->rrsets[$recordset->name.' '.$recordset->type] = $rrset;
				usort($recordset->records,
					function($a, $b) {
						return strnatcasecmp($a->content, $b->content);
					}
				);
				foreach($recordset->records as $record) {
					if($recordset->type == 'SOA') {
						// ns1.oslo.osa hostmaster.oslo.osa 2015112300 10800 3600 604800 3600
						list($primary_ns, $contact, $serial, $refresh, $retry, $expiry, $default_ttl) = explode(' ', $record->content);
						$this->soa = new SOA;
						$this->soa->ttl = $recordset->ttl;
						$this->soa->content = $record->content;
						$this->soa->primary_ns = $primary_ns;
						$this->soa->contact = $contact;
						$this->soa->serial = $serial;
						$this->soa->refresh = $refresh;
						$this->soa->retry = $retry;
						$this->soa->expiry = $expiry;
						$this->soa->default_ttl = $default_ttl;
					} elseif($recordset->type == 'NS' && $recordset->name == $this->name) {
						$this->nameservers[] = $record->content;
					}
					$this->rrsets[$recordset->name.' '.$recordset->type]->add_resource_record(new ResourceRecord($record));
				}
				foreach($recordset->comments as $comment) {
					$this->rrsets[$recordset->name.' '.$recordset->type]->add_comment(new Comment($comment));
				}
			}
		}
		return $this->rrsets;
	}

	public function add_changeset(ChangeSet $changeset) {
		global $active_user;
		$comment = $changeset->comment;
		$requester_id = is_null($changeset->requester) ? null : $changeset->requester->id;
		$stmt = $this->database->prepare('INSERT INTO "changeset" (zone_id, author_id, requester_id, change_date, comment, added, deleted) VALUES (?, ?, ?, NOW(), ?, 0, 0)');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->bindParam(2, $active_user->id, PDO::PARAM_INT);
		$stmt->bindParam(3, $requester_id, PDO::PARAM_INT);
		$stmt->bindParam(4, $comment, PDO::PARAM_STR);
		$stmt->execute();
		$changeset->id = $this->database->lastInsertId('changeset_id_seq');
	}

	public function list_changesets() {
		global $user_dir;
		$stmt = $this->database->prepare('SELECT * FROM "changeset" WHERE zone_id = ? ORDER BY id DESC');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->execute();
		$changesets = array();
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$row['author'] = $user_dir->get_user_by_id($row['author_id']);
			$row['requester'] = (is_null($row['requester_id']) ? null : $user_dir->get_user_by_id($row['requester_id']));
			$row['change_date'] = DateTime::createFromFormat('Y-m-d H:i:s.u', $row['change_date']);
			$changesets[] = new ChangeSet($row['id'], $row);
		}
		return $changesets;
	}

	public function get_changeset_by_id($id) {
		global $user_dir;
		$stmt = $this->database->prepare('SELECT * FROM "changeset" WHERE zone_id = ? AND id = ?');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->bindParam(2, $id, PDO::PARAM_INT);
		$stmt->execute();
		if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$row['author'] = $user_dir->get_user_by_id($row['author_id']);
			$row['change_date'] = DateTime::createFromFormat('Y-m-d H:i:s.u', $row['change_date']);
			return new ChangeSet($row['id'], $row);
		}
		throw new ChangeSetNotFound;
	}

	public function add_access(ZoneAccess $access) {
		$stmt = $this->database->prepare('INSERT INTO zone_access (zone_id, user_id, level) VALUES (?, ?, ?)');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->bindParam(2, $access->user->id, PDO::PARAM_INT);
		$stmt->bindParam(3, $access->level, PDO::PARAM_STR);
		try {
			$stmt->execute();
		} catch(PDOException $e) {
			if($e->getCode() == 23505) return;
			throw $e;
		}
	}

	public function delete_access(User $user) {
		$stmt = $this->database->prepare('DELETE FROM zone_access WHERE zone_id = ? AND user_id = ?');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->bindParam(2, $user->id, PDO::PARAM_INT);
		$stmt->execute();
	}

	public function list_access() {
		$stmt = $this->database->prepare('SELECT user_id, level FROM zone_access WHERE zone_id = ?');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->execute();
		$access = array();
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$rule = new ZoneAccess();
			$rule->user = new User($row['user_id']);
			$rule->level = $row['level'];
			$access[] = $rule;
		}
		return $access;
	}

	public function export_as_bind9_format() {
		$this->rrsets = null;
		$rrsets = $this->list_resource_record_sets();
		$output = "\$ORIGIN $this->name\n";
		$output .= "\$TTL {$this->soa->default_ttl}\n";
		foreach($rrsets as $rrset) {
			foreach($rrset->list_resource_records() as $rr) {
				$row = str_pad(($rr->disabled ? ';' : '').DNSName::abbreviate($rrset->name, $this->name), 30)." ";
				$row .= str_pad(DNSTime::abbreviate($rrset->ttl), 6)." ";
				$row .= str_pad($rrset->type, 6)." ";
				$row .= str_pad(DNSContent::bind9_format($rr->content, $rrset->type, $this->name), 30);
				$comments = $rrset->list_comments();
				if(count($comments) > 0) {
					$row .= " ; ";
					$count = 0;
					foreach($comments as $comment) {
						$count++;
						if($count > 1) $row .= " ";
						$row .= $comment->content;
					}
				}
				$output .= trim($row)."\n";
			}
		}
		return $output;
	}

	public function add_pending_update($update) {
		global $active_user;
		$stmt = $this->database->prepare('INSERT INTO pending_update (zone_id, author_id, request_date, raw_data) VALUES (?, ?, NOW(), ?)');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->bindParam(2, $active_user->id, PDO::PARAM_INT);
		$stmt->bindParam(3, $update, PDO::PARAM_LOB);
		$stmt->execute();
	}

	public function delete_pending_update(PendingUpdate $update) {
		$stmt = $this->database->prepare('DELETE FROM pending_update WHERE zone_id = ? AND id = ?');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->bindParam(2, $update->id, PDO::PARAM_INT);
		$stmt->execute();
	}

	public function get_pending_update_by_id($id) {
		$stmt = $this->database->prepare('SELECT * FROM pending_update WHERE zone_id = ? AND id = ?');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->bindParam(2, $id, PDO::PARAM_INT);
		$stmt->execute();
		if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$update = new PendingUpdate($row['id'], $row);
			$update->author = new User($row['author_id']);
			$update->request_date = DateTime::createFromFormat('Y-m-d H:i:s.u', $row['request_date']);
			$update->raw_data = stream_get_contents($row['raw_data']);
			return $update;
		}
		throw new PendingUpdateNotFound;
	}

	public function list_pending_updates() {
		$stmt = $this->database->prepare('SELECT * FROM pending_update WHERE zone_id = ?');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->execute();
		$updates = array();
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$update = new PendingUpdate($row['id'], $row);
			$update->author = new User($row['author_id']);
			$update->request_date = DateTime::createFromFormat('Y-m-d H:i:s.u', $row['request_date']);
			$update->raw_data = stream_get_contents($row['raw_data']);
			$updates[] = $update;
		}
		return $updates;
	}

	public function process_bulk_json_rrset_update($update, $author = null) {
		global $active_user, $config, $zone_dir;
		$rrsets = $this->list_resource_record_sets();
		ini_set('memory_limit', '512M');
		$trash = array();
		$changes = array();
		$errors = array();
		$revs_missing = array('A' => array(), 'AAAA' => array());
		$revs_updated = array();
		$update = json_decode($update);
		if(is_null($update)) throw new InvalidJSON(json_last_error_msg());
		if(!isset($update->actions) && !is_array($update->actions)) throw new BadData('No actions provided.');
		foreach($update->actions as $action) {
			try {
				$changes[] = $this->process_rrset_action($action, $trash, $revs_missing, $revs_updated);
			} catch(RuntimeException $e) {
				$errors[] = $e->getMessage();
			}
		}
		if(count($errors) > 0) {
			throw new BadData(implode(' ', $errors));
		}
		foreach($trash as $ref => $delete) {
			if($delete) {
				$rrset = $rrsets[$ref];
				$this->delete_resource_record_set($rrset);
			}
		}
		$changeset = new ChangeSet;
		if(!empty($update->comment)) {
			$changeset->comment = $update->comment;
		}
		if(!is_null($author)) {
			$changeset->requester = $author;
		}
		$this->commit_changes();
		$this->add_changeset($changeset);
		foreach($changes as $change) {
			$changeset->add_change($change);
		}
		$this->send_missing_reverse_zones_warning($revs_missing);
		// Update serial for every reverse zone updated as a consequence of changes to this zone
		// We wouldn't need to do this if not for https://github.com/PowerDNS/pdns/issues/3654
		foreach($revs_updated as $rev) {
			$rev->commit_changes();
		}
		// Create git commit for this change, including all affected reverse zones
		$git_commit_comment = "Zone {$this->name} edited via DNS UI\n";
		if(count($revs_updated) > 0) {
			$git_commit_comment .= "\nReverse zones updated:\n";
			foreach($revs_updated as $rev) {
				$git_commit_comment .= "* {$rev->name}\n";
			}
		}
		$git_commit_comment .= "\n{$config['web']['baseurl']}/zones/".urlencode($this->name).'#changelog#'.$changeset->id;
		if(!empty($update->comment)) {
			$git_commit_comment .= "\nChange comment: {$update->comment}";
		}
		$zone_dir->git_tracked_export(array_merge(array($this), $revs_updated), $git_commit_comment);
		$alert = new UserAlert;
		$alert->content = "Zone updated successfully.";
		$active_user->add_alert($alert);
	}

	private function process_rrset_action($update, &$trash, &$revs_missing, &$revs_updated) {
		global $active_user, $zone_dir;
		if(!is_object($update)) throw new BadData('Malformed update.');
		if(!(isset($update->name) && isset($update->type))) throw new BadData('Malformed action.');
		$change = new Change;
		if(isset($update->name)) $update->name = utf8_to_punycode(DNSName::canonify($update->name, $this->name));
		if(isset($update->oldname)) $update->oldname = utf8_to_punycode(DNSName::canonify($update->oldname, $this->name));
		else {
			$update->oldname = $update->name;
			$update->oldtype = $update->type;
		}
		if(($update->type == 'SOA' || $update->type == 'NS') && !$active_user->admin) return;
		switch($update->action) {
		case 'add':
			if(!isset($update->records) || !is_array($update->records)) throw new BadData('Malformed action');
			if(isset($this->rrsets[$update->name.' '.$update->type])) {
				throw new BadData('Tried to add a resource recordset that already exists: '.$update->name.' '.$update->type.'.');
			}
			$rrset = new ResourceRecordSet;
			$rrset->name = $update->name;
			$rrset->type = $update->type;
			if(!isset($update->ttl)) throw new BadData('Malformed recordset.');
			$rrset->ttl = DNSTime::expand($update->ttl);
			$trash[$update->name.' '.$update->type] = false;
			foreach($update->records as $record) {
				if(!(isset($record->content) && isset($record->enabled))) throw new BadData('Malformed record.');
				$record->content = DNSContent::encode($record->content, $update->type);
				$rr = new ResourceRecord;
				$rr->name = $update->name;
				$rr->type = $update->type;
				$rr->content = $record->content;
				$rr->disabled = ($record->enabled === 'No' || $record->enabled === false);
				$rr->{'set-ptr'} = $rr->disabled ? false : $zone_dir->check_reverse_record_zone($rr->type, $rr->content, $revs_missing, $revs_updated);
				$rrset->add_resource_record($rr);
			}
			if(isset($update->comment)) {
				$comment = new Comment;
				$comment->content = $update->comment;
				$comment->account = $active_user->uid;
				$rrset->add_comment($comment);
			}
			$change->after = serialize($rrset);
			$this->add_resource_record_set($rrset);
			break;
		case 'update':
			if(!isset($update->records) || !is_array($update->records)) throw new BadData('Malformed action.');
			if(!isset($this->rrsets[$update->oldname.' '.$update->oldtype])) {
				throw new BadData('Tried to update a non-existent resource recordset: '.$update->oldname.' '.$update->oldtype.'.');
			}
			$rrset = clone($this->rrsets[$update->oldname.' '.$update->oldtype]);
			$change->before = serialize($this->rrsets[$update->oldname.' '.$update->oldtype]);
			if($rrset->rename($update->name, $update->type)) {
				if(!isset($trash[$update->oldname.' '.$update->oldtype])) {
					$trash[$update->oldname.' '.$update->oldtype] = true;
				}
			}
			$trash[$update->name.' '.$update->type] = false;
			$rrset->clear_resource_records();
			$rrset->ttl = DNSTime::expand($update->ttl);
			$record_count = 0;
			foreach($update->records as $record) {
				if(!empty($record->delete)) continue;
				$record_count++;
				$record->content = DNSContent::encode($record->content, $update->type);
				$rr = new ResourceRecord;
				$rr->name = $update->name;
				$rr->type = $update->type;
				$rr->content = $record->content;
				$rr->disabled = ($record->enabled === 'No' || $record->enabled === false);
				$rr->{'set-ptr'} = $zone_dir->check_reverse_record_zone($rr->type, $rr->content, $revs_missing, $revs_updated);
				$rrset->add_resource_record($rr);
			}
			if(isset($update->comment) && $update->comment != $rrset->merge_comment_text()) {
				$rrset->clear_comments();
				$comment = new Comment;
				$comment->content = $update->comment;
				$comment->account = $active_user->uid;
				$rrset->add_comment($comment);
			}
			if($record_count == 0) {
				$this->delete_resource_record_set($rrset);
			} else {
				$change->after = serialize($rrset);
				$this->update_resource_record_set($rrset);
			}
			break;
		case 'delete':
			if(!isset($this->rrsets[$update->oldname.' '.$update->oldtype])) {
				throw new BadData('Tried to delete a non-existent resource recordset: '.$update->oldname.' '.$update->oldtype.'.');
			}
			$change->before = serialize($this->rrsets[$update->oldname.' '.$update->oldtype]);
			$this->delete_resource_record_set($this->rrsets[$update->oldname.' '.$update->oldtype]);
			break;
		}
		return $change;
	}

	private function send_missing_reverse_zones_warning($revs_missing) {
		global $config;
		$revs_missing_count = count($revs_missing['A']) + count($revs_missing['AAAA']);
		if($revs_missing_count > 0) {
			// Reverse zones are missing - alert the people in charge
			if(isset($config['email']['report_address'])) {
				$mail = new Email;
				$mail->add_recipient($config['email']['report_address'], $config['email']['report_name']);
				$mail->subject = $revs_missing_count.' new DNS resource record'.($revs_missing_count == 1 ? '' : 's').' in '.punycode_to_utf8($this->name).' '.($revs_missing_count == 1 ? 'needs a reverse zone' : 'need reverse zones');
				$mail->body = "The following records were added or updated in the ".punycode_to_utf8($this->name)." zone:\n\n";
				foreach($revs_missing['A'] as $rev_missing) {
					$mail->body .= "    A: {$rev_missing}\n";
				}
				foreach($revs_missing['AAAA'] as $rev_missing) {
					$mail->body .= " AAAA: {$rev_missing}\n";
				}
				$mail->body .= "\nBut no appropriate reverse zone could be found.\n";
				if(count($revs_missing['A']) > 0) {
					$mail->body .= "\nNew IPv4 reverse zones can be added at {$config['web']['baseurl']}/zones#reverse4";
				}
				if(count($revs_missing['AAAA']) > 0) {
					$mail->body .= "\nNew IPv6 reverse zones can be added at {$config['web']['baseurl']}/zones#reverse6";
				}
				$mail->send();
			}
		}
	}
}

class SOA {
	public $content;
	public $ttl;
	public $primary_ns;
	public $contact;
	public $serial;
	public $refresh;
	public $retry;
	public $expiry;
	public $default_ttl;
}

class ChangeSetNotFound extends RuntimeException {}
class PendingUpdateNotFound extends RuntimeException {}
