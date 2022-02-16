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

/**
* Class that represents a DNS zone.
*/
class Zone extends Record {
	/**
	* Defines the database table that this object is stored in
	*/
	protected $table = 'zone';
	/**
	* PowerDNS communication object
	*/
	private $powerdns;
	/**
	* List of resource record sets (RRsets) in the zone
	*/
	private $rrsets = null;
	/**
	* Details from the zone's SOA record
	*/
	private $soa = null;
	/**
	* A list of nameserver addresses for this zone
	*/
	private $nameservers = null;
	/**
	* Crypto keys for the zone as reported by PowerDNS
	*/
	private $cryptokeys = null;
	/**
	* Flag for API-RECTIFY being enabled
	*/
	private $api_rectify = null;
	/**
	* List of changes to be applied to the zone when doing ->commit_changes()
	*/
	private $changes = array();

	public function __construct($id = null, $preload_data = array()) {
		parent::__construct($id, $preload_data);
		global $powerdns;
		$this->powerdns = $powerdns;
	}

	/**
	* Magic getter method with special cases for soa and nameservers.
	* @param string $field to retrieve
	* @return mixed data stored in field
	*/
	public function &__get($field) {
		switch($field) {
		case 'soa':
			if(is_null($this->soa)) $this->list_resource_record_sets();
			return $this->soa;
		case 'nameservers':
			if(is_null($this->nameservers)) $this->list_resource_record_sets();
			return $this->nameservers;
		case 'api_rectify':
			if(is_null($this->api_rectify)) $this->list_resource_record_sets();
			return $this->api_rectify;
		default:
			return parent::__get($field);
		}
	}

	/**
	* Magic setter method - special handling of nameservers attribute.
	* @param string $field to update
	* @param mixed $value to store in field
	*/
	public function __set($field, $value) {
		switch($field) {
		case 'nameservers':
			$this->nameservers = $value;
			break;
		case 'api_rectify':
			$this->api_rectify = $value;
			break;
		default:
			parent::__set($field, $value);
		}
	}

	/**
	* Add a resource record set (RRset) to this zone
	* @param ResourceRecordSet $rrset to add
	*/
	public function add_resource_record_set(ResourceRecordSet $rrset) {
		$this->add_or_update_resource_record_set($rrset);
		syslog_report(LOG_INFO, "zone={$this->name};object=rrset;action=add;name={$rrset->name}");
	}

	/**
	* Update an existing resource record set (RRset) in this zone.
	* @param ResourceRecordSet $rrset updated data
	*/
	public function update_resource_record_set(ResourceRecordSet $rrset) {
		$this->add_or_update_resource_record_set($rrset);
		syslog_report(LOG_INFO, "zone={$this->name};object=rrset;action=update;name={$rrset->name}");
	}

	/**
	* Add or update a resource record set (RRset) in this zone.
	* Generates the update to be sent to the PowerDNS API and stores it ready for sending.
	* @param ResourceRecordSet $rrset data
	*/
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

	/**
	* Delete a resource record set (RRset) from this zone.
	* Generates the update to be sent to the PowerDNS API and stores it ready for sending.
	* @param ResourceRecordSet $rrset to be deleted
	*/
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

	/**
	* Send all stored changes to the PowerDNS API.
	* @throws ResourceRecordInvalid if update failed
	*/
	public function commit_changes() {
		usort($this->changes, function($a, $b) { return strcmp($a->changetype, $b->changetype); });
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

	/**
	* Tell PowerDNS API to trigger a notify for this zone.
	*/
	public function send_notify() {
		$this->powerdns->put('zones/'.urlencode($this->pdns_id).'/notify', '');
	}

	/**
	* Tell PowerDNS API to update this zone and update local DB also.
	* @param string $account new value
	*/
	public function update() {
		global $config;
		$update = new StdClass;
		$update->kind = $this->kind;
		$update->account = $this->account;
		if(isset($config['dns']['dnssec']) && $config['dns']['dnssec'] == 1) {
			$update->dnssec = (bool)$this->dnssec;
			$update->api_rectify = (bool)$this->api_rectify;
		}
		$response = $this->powerdns->put('zones/'.urlencode($this->pdns_id), $update);
		parent::update();
	}

	/**
	* List all resource record sets (RRsets) in this zone.
	* Fetch and parse the data from the PowerDNS API if we do not yet have it.
	* Also parse data from SOA RRset and store results in $this->soa.
	* @return array of ResourceRecordSet objects
	*/
	public function &list_resource_record_sets() {
		if(is_null($this->rrsets)) {
			$this->rrsets = array();
			$this->nameservers = array();
			try {
				$data = $this->powerdns->get('zones/'.urlencode($this->pdns_id));
			} catch(Pest_InvalidRecord $e) { // before PowerDNS 4.2
				throw new ZoneNotFoundInPowerDNS;
			} catch(Pest_NotFound $e) { // 404 since PowerDNS 4.2
			    throw new ZoneNotFoundInPowerDNS;;
			}
			$possible_bad_data = array();
			usort($data->rrsets,
				function($a, $b) {
					if($a->type == 'SOA' && $b->type != 'SOA') return -1;
					if($b->type == 'SOA' && $a->type != 'SOA') return 1;
					$aname = implode(',', array_reverse(explode('.', $a->name)));
					$bname = implode(',', array_reverse(explode('.', $b->name)));
					if($aname == $bname) {
						if($a->type == 'NS' && $b->type != 'NS') return -1;
						if($b->type == 'NS' && $a->type != 'NS') return 1;
						return strcasecmp($a->type, $b->type);
					} else {
						return strnatcasecmp($aname, $bname);
					}
				}
			);
			foreach($data->rrsets as $recordset) {
				if(isset($this->rrsets[$recordset->name.' '.$recordset->type])) {
					// This is not supposed to happen - ordinarily there would only be 1 object in the JSON data for each
					// name/type combination (1 per RRSet), but in some cases PowerDNS sends comments and RRs for an RRSet
					// separated into multiple objects in the JSON data with the comments object having a TTL of 0.
					// Despite many attempts I have been unable to find what triggers this, so am adding this workaround.
					$this->rrsets[$recordset->name.' '.$recordset->type]->ttl = max($this->rrsets[$recordset->name.' '.$recordset->type]->ttl, $recordset->ttl);
				} else {
					$rrset = new ResourceRecordSet;
					$rrset->name = $recordset->name;
					$rrset->type = $recordset->type;
					$rrset->ttl = $recordset->ttl;
					$this->rrsets[$recordset->name.' '.$recordset->type] = $rrset;
				}
				usort($recordset->records,
					function($a, $b) {
						return strnatcasecmp($a->content, $b->content);
					}
				);
				if(count($recordset->records) == 0) {
					// This is a workaround for bad data in the PowerDNS database - should a comment exist for an RRset that
					// doesn't exist in the records table, we should remove it from our list
					$possible_bad_data[$recordset->name.' '.$recordset->type] = true;
				}
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
			foreach($possible_bad_data as $key => $null) {
				if(count($this->rrsets[$key]->list_resource_records()) == 0) {
					// RRset still has no RRs
					unset($this->rrsets[$key]);
					error_log("Stray comment for $key in zone {$this->name}");
				}
			}
			if(isset($data->api_rectify)) {
				$this->api_rectify = $data->api_rectify;
			}
		}
		return $this->rrsets;
	}

	/**
	* Get all cryptokey metadata this zone.
	* Fetch and parse the data from the PowerDNS API if we do not yet have it.
	* @return StdClass containing all cryptokey metadata
	*/
	public function &get_cryptokeys() {
		if(is_null($this->cryptokeys)) {
			try {
				$data = $this->powerdns->get('zones/'.urlencode($this->pdns_id).'/cryptokeys');
			} catch(Pest_InvalidRecord $e) {
				$data = array();
			}
		}
		$this->cryptokeys = $data;
		return $this->cryptokeys;
	}

	/**
	* Add a ChangeSet to the database for this zone.
	* @param ChangeSet $changeset containing changes to be added
	*/
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

	/**
	* List all ChangeSet objects associated with this zone.
	* @param $filters array with optional keys 'comment', 'start_date' and 'end_date' to filter results
	* @return array of ChangeSet objects
	*/
	public function list_changesets($filters=[]) {
		global $user_dir;

		$items_per_page = 200;  # arbitrary
		$comment = null;
		$start_date = null;
		$end_date = null;
		$limit = null;
		$offset = null;
		if (isset($filters['comment'])) {
			$comment = "%" . $filters['comment'] . "%";
		}
		if (isset($filters['start_date'])) {
			$start_date = $filters['start_date']->format('Y-m-d 00:00:00');
		}
		if (isset($filters['end_date'])) {
			$end_date = $filters['end_date']->format('Y-m-d 23:59:59');
		}
		if (isset($filters['page'])) {
			$limit = $items_per_page;
			$offset = $items_per_page * ($filters['page'] - 1);
		} # otherwise, keep limit and offset NULL to disable pagination

		$stmt = $this->database->prepare('
			SELECT *, count(*) OVER () AS row_count
			FROM "changeset"
			WHERE
				zone_id = ? AND
				(? OR comment ILIKE ?) AND
				(? OR change_date >= ?) AND  -- start_date
				(? OR change_date < ?)       -- end_date
			ORDER BY id DESC
			LIMIT ?
			OFFSET ?');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);

		$stmt->bindValue(2, is_null($comment), PDO::PARAM_BOOL);
		$stmt->bindParam(3, $comment, PDO::PARAM_STR);

		$stmt->bindValue(4, is_null($start_date), PDO::PARAM_BOOL);
		$stmt->bindParam(5, $start_date, PDO::PARAM_STR);

		$stmt->bindValue(6, is_null($end_date), PDO::PARAM_BOOL);
		$stmt->bindParam(7, $end_date, PDO::PARAM_STR);

		$stmt->bindParam(8, $limit, PDO::PARAM_INT);
		$stmt->bindParam(9, $offset, PDO::PARAM_INT);

		$stmt->execute();
		$changesets = array();
		$row_count = 0;
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$row['author'] = $user_dir->get_user_by_id($row['author_id']);
			$row['requester'] = (is_null($row['requester_id']) ? null : $user_dir->get_user_by_id($row['requester_id']));
			$row['change_date'] = parse_postgres_date($row['change_date']);
			$changesets[] = new ChangeSet($row['id'], $row);
			$row_count = $row['row_count'];
		}
		$page_count = ceil($row_count / $items_per_page);
		return array($page_count, $changesets);
	}

	/**
	* Fetch a specific ChangeSet object for this zone by its ID.
	* @param int $id of the ChangeSet
	* @return ChangeSet matching the specified ID
	*/
	public function get_changeset_by_id($id) {
		global $user_dir;
		$stmt = $this->database->prepare('SELECT * FROM "changeset" WHERE zone_id = ? AND id = ?');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->bindParam(2, $id, PDO::PARAM_INT);
		$stmt->execute();
		if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$row['author'] = $user_dir->get_user_by_id($row['author_id']);
			$row['change_date'] = parse_postgres_date($row['change_date']);
			return new ChangeSet($row['id'], $row);
		}
		throw new ChangeSetNotFound;
	}

	/**
	* Add an access rule for this zone, granting user access.
	* @param ZoneAccess $access rule to add
	*/
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

	/**
	* Revoke a user's access to this zone.
	* @param User $user to revoke access for
	*/
	public function delete_access(User $user) {
		$stmt = $this->database->prepare('DELETE FROM zone_access WHERE zone_id = ? AND user_id = ?');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->bindParam(2, $user->id, PDO::PARAM_INT);
		$stmt->execute();
	}

	/**
	* List all access rule applied to this zone.
	* @return array of ZoneAccess objects
	*/
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

	/**
	* Export the entire zone in bind9 zone file format.
	* @return string exported zone
	*/
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
				$comments = $rrset->merge_comment_text();
				if($comments !== '') {
					$row .= " ; $comments";
				}
				$output .= trim($row)."\n";
			}
		}
		return $output;
	}

	/**
	* Add a requested (pending) update to this zone.
	* @param string $update JSON-encoded update
	*/
	public function add_pending_update($update) {
		global $active_user;
		$stmt = $this->database->prepare('INSERT INTO pending_update (zone_id, author_id, request_date, raw_data) VALUES (?, ?, NOW(), ?)');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->bindParam(2, $active_user->id, PDO::PARAM_INT);
		$stmt->bindParam(3, $update, PDO::PARAM_LOB);
		$stmt->execute();
	}

	/**
	* Delete a requested (pending) update from this zone.
	* @param PendingUpdate $update to delete
	*/
	public function delete_pending_update(PendingUpdate $update) {
		$stmt = $this->database->prepare('DELETE FROM pending_update WHERE zone_id = ? AND id = ?');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->bindParam(2, $update->id, PDO::PARAM_INT);
		$stmt->execute();
	}

	/**
	* Fetch a specific pending update for this zone by its ID.
	* @param PendingUpdate $update to delete
	* @throws PendingUpdateNotFound if no update exists with the specified ID
	*/
	public function get_pending_update_by_id($id) {
		$stmt = $this->database->prepare('SELECT * FROM pending_update WHERE zone_id = ? AND id = ?');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->bindParam(2, $id, PDO::PARAM_INT);
		$stmt->execute();
		if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$update = new PendingUpdate($row['id'], $row);
			$update->author = new User($row['author_id']);
			$update->request_date = parse_postgres_date($row['request_date']);
			$update->raw_data = stream_get_contents($row['raw_data']);
			return $update;
		}
		throw new PendingUpdateNotFound;
	}

	/**
	* List all pending updates for this zone.
	* @return array of PendingUpdate objects
	*/
	public function list_pending_updates() {
		$stmt = $this->database->prepare('SELECT * FROM pending_update WHERE zone_id = ?');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->execute();
		$updates = array();
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$update = new PendingUpdate($row['id'], $row);
			$update->author = new User($row['author_id']);
			$update->request_date = parse_postgres_date($row['request_date']);
			$update->raw_data = stream_get_contents($row['raw_data']);
			$updates[] = $update;
		}
		return $updates;
	}

	/**
	* Add a deletion request for this zone.
	*/
	public function add_delete_request() {
		global $active_user;
		$stmt = $this->database->prepare('INSERT INTO zone_delete (zone_id, requester_id, request_date) VALUES (?, ?, NOW())');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->bindParam(2, $active_user->id, PDO::PARAM_INT);
		try {
			$stmt->execute();
		} catch(PDOException $e) {
			if($e->getCode() == 23505) return;
			throw $e;
		}
	}

	/**
	* Get the deletion request for this zone (if any).
	*/
	public function get_delete_request() {
		$stmt = $this->database->prepare('SELECT * FROM zone_delete WHERE zone_id = ?');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->execute();
		if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$row['requester'] = new User($row['requester_id']);
			$row['request_date'] = parse_postgres_date($row['request_date']);
			if(!is_null($row['confirmer_id'])) {
				$row['confirmer'] = new User($row['confirmer_id']);
				$row['confirm_date'] = parse_postgres_date($row['confirm_date']);
			}
			return $row;
		}
		return null;
	}

	/**
	* Cancel the deletion request for this zone.
	*/
	public function cancel_delete_request() {
		$stmt = $this->database->prepare('DELETE FROM zone_delete WHERE zone_id = ? AND confirm_date IS NULL');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->execute();
	}

	/**
	* Confirm the deletion request for this zone.
	*/
	public function confirm_delete_request() {
		global $active_user, $zone_dir;
		$stmt = $this->database->prepare('SELECT requester_id FROM zone_delete WHERE zone_id = ?');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->execute();
		if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			if($row['requester_id'] != $active_user->id) {
				$zone_export = $this->export_as_bind9_format();
				$stmt = $this->database->prepare('UPDATE zone_delete SET confirmer_id = ?, confirm_date = NOW(), zone_export = ? WHERE zone_id = ?');
				$stmt->bindParam(1, $active_user->id, PDO::PARAM_INT);
				$stmt->bindParam(2, $zone_export, PDO::PARAM_LOB);
				$stmt->bindParam(3, $this->id, PDO::PARAM_INT);
				$stmt->execute();
				if($stmt->rowCount() == 1) {
					$this->powerdns->delete('zones/'.urlencode($this->pdns_id));
					$zone_dir->git_tracked_delete($this, 'Zone '.$this->name.' deleted via DNS UI');
				}
			}
		}
	}

	/**
	* Remove the deletion record for this zone.
	*/
	public function remove_delete_record() {
		$stmt = $this->database->prepare('DELETE FROM zone_delete WHERE zone_id = ? AND confirm_date IS NOT NULL');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->execute();
	}

	/**
	* Restore a deleted zone.
	*/
	public function restore() {
		global $zone_dir, $config;
		$initial_limit = 10; // Max records to send in first request - workaround for https://github.com/PowerDNS/pdns/issues/6111
		$batch_limit = 2500; // Max records to send in subsequent requests - avoid hitting limits in PowerDNS
		$deletion = $this->get_delete_request();
		$zonefile = new BindZonefile($deletion['zone_export']);
		$rrsets = $zonefile->parse_into_rrsets($this, true);
		$data = new StdClass;
		$data->name = $this->name;
		$data->kind = $this->kind;
		$data->nameservers = array();
		$data->rrsets = array();
		foreach($rrsets as $rrset) {
			$recordset = new StdClass;
			$recordset->name = $rrset->name;
			$recordset->type = $rrset->type;
			$recordset->ttl = $rrset->ttl;
			$recordset->records = array();
			$recordset->comments = array();
			foreach($rrset->list_resource_records() as $rr) {
				$record = new StdClass;
				$record->content = $rr->content;
				$record->disabled = $rr->disabled;
				$recordset->records[] = $record;
			}
			foreach($rrset->list_comments() as $c) {
				$comment = new StdClass;
				$comment->name = $c->name;
				$comment->type = $c->type;
				$comment->content = $c->content;
				$comment->account = $c->account;
				$comment->modified_at = $c->modified_at;
				$recordset->comments[] = $comment;
			}
			$data->rrsets[] = $recordset;
		}
		$data->soa_edit_api = isset($config['powerdns']['soa_edit_api']) ? $config['powerdns']['soa_edit_api'] : 'INCEPTION-INCREMENT';
		$data->account = $this->account;
		$data->dnssec = (bool)$this->dnssec;
		$remaining_rrsets = array_slice($data->rrsets, $initial_limit);
		$data->rrsets = array_slice($data->rrsets, 0, $initial_limit);
		$response = $this->powerdns->post('zones', $data);
		$this->pdns_id = $response->id;
		$this->serial = $response->serial;
		while(count($remaining_rrsets) > 0) {
			$patch = new StdClass;
			$patch->rrsets = $remaining_rrsets;
			$remaining_rrsets = array_slice($patch->rrsets, $batch_limit);
			$patch->rrsets = array_slice($patch->rrsets, 0, $batch_limit);
			foreach($patch->rrsets as $ref => $value) {
				$patch->rrsets[$ref]->changetype = 'REPLACE';
			}
			$response = $this->powerdns->patch('zones/'.urlencode($this->pdns_id), $patch);
		}
		$this->send_notify();
		$zone_dir->git_tracked_export(array($this), 'Zone '.$this->name.' restored via DNS UI');
		$stmt = $this->database->prepare('DELETE FROM zone_delete WHERE zone_id = ? AND confirm_date IS NOT NULL');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->execute();
	}

	/**
	* Given a JSON-encoded string with a list of changes to be made to a zone, process and perform those changes.
	* @param string $update JSON-encoded list of changes
	*/
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
		if(isset($config['web']['force_change_comment']) && intval($config['web']['force_change_comment']) == 1 && empty($update->comment)) throw new BadData('A change comment must be provided.');
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
		// Create git commit for this change, including all affected reverse zones
		$git_commit_comment = "Zone {$this->name} edited via DNS UI\n";
		if(count($revs_updated) > 0) {
			$git_commit_comment .= "\nReverse zones updated:\n";
			foreach($revs_updated as $rev) {
				$git_commit_comment .= "* {$rev->name}\n";
			}
		}
		$git_commit_comment .= "\n{$config['web']['baseurl']}/zones/".urlencode(DNSZoneName::unqualify($this->name)).'#changelog#'.$changeset->id;
		if(!empty($update->comment)) {
			$git_commit_comment .= "\nChange comment: {$update->comment}";
		}
		$zone_dir->git_tracked_export(array_merge(array($this), $revs_updated), $git_commit_comment);
		$alert = new UserAlert;
		$alert->content = "Zone updated successfully.";
		$active_user->add_alert($alert);
	}

	/**
	* Process an RRset update provided by the UI or API and add the relevant changes to this zone.
	* @param StdClass $update changes to be made
	* @param array $trash keep track of RRsets that should be deleted as the result of a rename
	* @param array $revs_missing keep track of reverse zones that are missing
	* @param array $revs_updated keep track of reverse zones that will be updated
	*/
	private function process_rrset_action($update, &$trash, &$revs_missing, &$revs_updated) {
		global $active_user, $config, $zone_dir;
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

		if(isset($config['dns']['autocreate_reverse_records'])) {
			$autocreate_ptr = (bool)$config['dns']['autocreate_reverse_records'];
		} else {
			$autocreate_ptr = true; # enabled by default
		}

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
				$record->content = DNSContent::encode($record->content, $update->type, $this->name);
				$rr = new ResourceRecord;
				$rr->content = $record->content;
				$rr->disabled = ($record->enabled === 'No' || $record->enabled === false);
				if(!$autocreate_ptr || $rr->disabled) {
					$rr->{'set-ptr'} = false;
				} else {
					$rr->{'set-ptr'} = $zone_dir->check_reverse_record_zone($rrset->name, $rrset->type, $rr->content, $revs_missing, $revs_updated);
				}
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
				$record->content = DNSContent::encode($record->content, $update->type, $this->name);
				$rr = new ResourceRecord;
				$rr->content = $record->content;
				$rr->disabled = ($record->enabled === 'No' || $record->enabled === false);
				if(!$autocreate_ptr || $rr->disabled) {
					$rr->{'set-ptr'} = false;
				} else {
					$rr->{'set-ptr'} = $zone_dir->check_reverse_record_zone($rrset->name, $rrset->type, $rr->content, $revs_missing, $revs_updated);
				}
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

	/**
	* Send emails to the configured report address notifying them of records that were modified
	* where we are unable to auto-create a reverse entry due to missing reverse zones.
	* @param array $revs_missing lists of A and AAAA records affected
	*/
	private function send_missing_reverse_zones_warning($revs_missing) {
		global $config;
		$revs_missing_count = count($revs_missing['A']) + count($revs_missing['AAAA']);
		if($revs_missing_count > 0) {
			// Reverse zones are missing - alert the people in charge
			if(isset($config['email']['report_address'])) {
				$mail = new Email;
				$mail->add_recipient($config['email']['report_address'], $config['email']['report_name']);
				$mail->subject = $revs_missing_count.' new DNS resource record'.($revs_missing_count == 1 ? '' : 's').' in '.DNSZoneName::unqualify(punycode_to_utf8($this->name)).' '.($revs_missing_count == 1 ? 'needs a reverse zone' : 'need reverse zones');
				$mail->body = "The following records were added or updated in the ".DNSZoneName::unqualify(punycode_to_utf8($this->name))." zone:\n\n";
				foreach($revs_missing['A'] as $rev_missing) {
					$mail->body .= "    A: {$rev_missing['address']} ({$rev_missing['name']})\n";
				}
				foreach($revs_missing['AAAA'] as $rev_missing) {
					$mail->body .= " AAAA: {$rev_missing['address']} ({$rev_missing['name']})\n";
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
	/**
	* Entire text content of the SOA record
	*/
	public $content;
	/**
	* TTL of the SOA record
	*/
	public $ttl;
	/**
	* 1st field in the SOA Record - primary nameserver
	*/
	public $primary_ns;
	/**
	* 2nd field in the SOA Record - contact address
	*/
	public $contact;
	/**
	* 3rd field in the SOA Record - zone serial
	*/
	public $serial;
	/**
	* 4th field in the SOA Record - refresh interval
	*/
	public $refresh;
	/**
	* 5th field in the SOA Record - retry interval
	*/
	public $retry;
	/**
	* 6th field in the SOA Record - expiry interval
	*/
	public $expiry;
	/**
	* 7th field in the SOA Record - default (NXDOMAIN) ttl
	*/
	public $default_ttl;
}

class ZoneNotFoundInPowerDNS extends RuntimeException {}
class ChangeSetNotFound extends RuntimeException {}
class PendingUpdateNotFound extends RuntimeException {}
