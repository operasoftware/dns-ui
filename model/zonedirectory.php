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
* Class for reading/writing to the list of Zone objects in the database.
*/
class ZoneDirectory extends DBDirectory {
	/**
	* PowerDNS communication object
	*/
	private $powerdns;
	/**
	* Cache of zone data returned from PowerDNS
	*/
	private $powerdns_zones = null;

	public function __construct() {
		parent::__construct();
		global $powerdns;
		$this->powerdns = $powerdns;
	}

	/**
	* Add a zone to the database.
	* @param Zone $zone to be added
	*/
	public function add_zone(Zone $zone) {
		$stmt = $this->database->prepare('INSERT INTO zone (pdns_id, name, serial, kind, account, dnssec) VALUES (?, ?, ?, ?, ?, ?)');
		$stmt->bindParam(1, $zone->pdns_id, PDO::PARAM_STR);
		$stmt->bindParam(2, $zone->name, PDO::PARAM_STR);
		$stmt->bindParam(3, $zone->serial, PDO::PARAM_INT);
		$stmt->bindParam(4, $zone->kind, PDO::PARAM_STR);
		$stmt->bindParam(5, $zone->account, PDO::PARAM_STR);
		$stmt->bindParam(6, $zone->dnssec, PDO::PARAM_INT);
		try {
			$stmt->execute();
			$zone->id = $this->database->lastInsertId('zone_id_seq');
		} catch(PDOException $e) {
			if($e->getCode() == 23505) {
				// Zone already exists in the database
				$stmt = $this->database->prepare('SELECT id FROM zone WHERE name = ?');
				$stmt->bindParam(1, $zone->name, PDO::PARAM_STR);
				$stmt->execute();
				if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
					$zone->id = $row['id'];
				}
			} else {
				throw $e;
			}
		}
	}

	/**
	* Create a new zone in PowerDNS and add to the database.
	* @param Zone $zone to be created
	*/
	public function create_zone($zone) {
		global $config;
		$data = new StdClass;
		$data->name = $zone->name;
		$data->kind = $zone->kind;
		$data->nameservers = $zone->nameservers;
		$data->rrsets = array();
		foreach($zone->list_resource_record_sets() as $rrset) {
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
		$data->account = $zone->account;
		$data->dnssec = (bool)$zone->dnssec;
		$response = $this->powerdns->post('zones', $data);
		$zone->pdns_id = $response->id;
		$zone->serial = $response->serial;
		$this->add_zone($zone);
		$zone->send_notify();
		$this->git_tracked_export(array($zone), 'Zone '.$zone->name.' created via DNS UI');
		syslog_report(LOG_INFO, "zone={$zone->name};object=zone;action=add;status=succeeded");
	}

	/**
	* List all zones in PowerDNS and update list in database to match.
	* @param array $include list of extra data to include in response
	* @return array of Zone objects indexed by pdns_id
	*/
	public function list_zones($include = array()) {
		$this->database->query('BEGIN WORK');
		$this->database->query('LOCK TABLE zone');
		$fields = array('zone.*');
		$joins = array();
		foreach($include as $field) {
			switch($field) {
			case 'pending_updates':
				$fields[] = 'COUNT(pending_update.id) as pending_updates';
				$joins[] = 'LEFT JOIN pending_update ON pending_update.zone_id = zone.id';
				break;
			}
		}
		$stmt = $this->database->prepare('
			SELECT '.implode(', ', $fields).'
			FROM zone '.implode(" ", $joins).'
			GROUP BY zone.id
			ORDER BY zone.name
		');
		$stmt->execute();
		$zones_by_pdns_id = array();
		$current_zones = array();
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$zones_by_pdns_id[$row['pdns_id']] = new Zone($row['id'], $row);
		}
		if(is_null($this->powerdns_zones)) {
			$this->powerdns_zones = $this->powerdns->get('zones');
			foreach($this->powerdns_zones as $pdns_zone) {
				if(!isset($zones_by_pdns_id[$pdns_zone->id])) {
					$zone = new Zone;
					$zone->pdns_id = $pdns_zone->id;
					$zone->name = $pdns_zone->name;
					$zone->kind = $pdns_zone->kind;
					$zone->serial = $pdns_zone->serial;
					$zone->account = $pdns_zone->account;
					$zone->dnssec = $pdns_zone->dnssec;
					$this->add_zone($zone);
					$zones_by_pdns_id[$zone->pdns_id] = $zone;
					$current_zones[$zone->pdns_id] = true;
				} else {
					$fields = array('serial' => PDO::PARAM_INT, 'kind' => PDO::PARAM_STR, 'account' => PDO::PARAM_STR, 'dnssec' => PDO::PARAM_INT);
					foreach($fields as $field => $type) {
						if($zones_by_pdns_id[$pdns_zone->id]->{$field} != $pdns_zone->{$field}) {
							$zones_by_pdns_id[$pdns_zone->id]->{$field} = $pdns_zone->{$field};
							$stmt = $this->database->prepare('UPDATE zone SET '.$field.' = ? WHERE id = ?');
							$stmt->bindParam(1, $pdns_zone->{$field}, $type);
							$stmt->bindParam(2, $zones_by_pdns_id[$pdns_zone->id]->id, PDO::PARAM_INT);
							$stmt->execute();
						}
					}
					$current_zones[$pdns_zone->id] = true;
					if(!$zones_by_pdns_id[$pdns_zone->id]->active) {
						$stmt = $this->database->prepare('UPDATE zone SET active = true WHERE id = ?');
						$stmt->bindParam(1, $zones_by_pdns_id[$pdns_zone->id]->id, PDO::PARAM_INT);
						$stmt->execute();
						$zones_by_pdns_id[$pdns_zone->id]->active = true;
					}
				}
			}
			foreach($zones_by_pdns_id as $pdns_id => &$zone) {
				if(!isset($current_zones[$zone->pdns_id]) && $zone->active) {
					$stmt = $this->database->prepare('UPDATE zone SET active = false WHERE id = ?');
					$stmt->bindParam(1, $zone->id, PDO::PARAM_INT);
					$stmt->execute();
					$zone->active = false;
				}
				if(!$zone->active) {
					unset($zones_by_pdns_id[$pdns_id]);
				}
			}
		}
		$this->database->query('COMMIT WORK');
		return $zones_by_pdns_id;
	}

	/**
	* Fetch the zone matching the specific name.
	* @param string $name of zone to fetch
	* @return Zone object
	* @throws ZoneNotFound if no zone exists with that name in the database
	*/
	public function get_zone_by_name($name) {
		$stmt = $this->database->prepare('SELECT * FROM zone WHERE name = ?');
		$stmt->bindParam(1, $name, PDO::PARAM_STR);
		$stmt->execute();
		if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$zone = new Zone($row['id'], $row);
		} else {
			throw new ZoneNotFound;
		}
		return $zone;
	}

	/**
	* Fetch the list of values for the "account" metadata field across all zones.
	* @return array of string values
	*/
	public function list_accounts() {
		$stmt = $this->database->prepare('SELECT DISTINCT(account) AS account FROM zone ORDER BY account');
		$stmt->execute();
		$accounts = array();
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$accounts[] = $row['account'];
		}
		return $accounts;
	}

	/**
	* Check the list of zones to see if a suitable reverse zone exists for the forward record.
	* @param string $name of DNS record
	* @param string $type of DNS record
	* @param string $address that DNS record points to
	* @param array $revs_missing keep track of reverse zones that are missing
	* @param array $revs_updated keep track of reverse zones that will be updated
	*/
	public function check_reverse_record_zone($name, $type, $address, &$revs_missing, &$revs_notify) {
		global $zone_dir, $active_user;

		if($type == 'A') {
			$reverse_address = implode('.', array_reverse(explode('.', $address))).'.in-addr.arpa.';
		} elseif($type == 'AAAA') {
			$address = ipv6_address_expand($address);
			$reverse_address = implode('.', array_reverse(str_split(str_replace(':', '', $address)))).'.ip6.arpa.';
		} else {
			return false;
		}
		$reverse_zone_name = $reverse_address;
		// Find an appropriate reverse zone by starting with the full domain name, and
		// removing subdomains until we find a match or run out of things to remove
		do {
			try {
				$reverse_zone = $zone_dir->get_zone_by_name($reverse_zone_name);
				// See if a record already exists for this IP
				foreach($reverse_zone->list_resource_record_sets() as $rrset) {
					if($rrset->name == $reverse_address) {
						if($rrset->type == 'PTR') {
							$alert = new UserAlert;
							$alert->escaping = ESC_NONE;
							$alert->content = 'Reverse record already exists for '.hesc($address).' in <a href="'.rrurl('/zones/'.urlencode(DNSZoneName::unqualify($reverse_zone->name))).'" class="alert-link">'.hesc(DNSZoneName::unqualify($reverse_zone->name)).'</a>. Not modifying existing PTR record from '.$rrset->merge_content_text().' to '.$name;
							$alert->class = 'warning';
							$active_user->add_alert($alert);
							return false;
						}
						if($rrset->type == 'CNAME') {
							$rr = reset($rrset->list_resource_records());
							$alert = new UserAlert;
							$alert->escaping = ESC_NONE;
							$alert->content = 'Reverse record delegated to '.hesc($rr->content).' for '.hesc($address).' in <a href="'.rrurl('/zones/'.urlencode(DNSZoneName::unqualify($reverse_zone->name))).'" class="alert-link">'.hesc(DNSZoneName::unqualify($reverse_zone->name)).'</a>. Not creating PTR record for '.$name;
							$alert->class = 'warning';
							$active_user->add_alert($alert);
							return false;
						}
					}
				}
				// Add reverse zone to list of zones to send a notify for
				$revs_notify[$reverse_zone->pdns_id] = $reverse_zone;
				return true;
			} catch(ZoneNotFound $e) {
			}
		} while($this->remove_subdomain($reverse_zone_name));
		$alert = new UserAlert;
		$alert->content = "No suitable reverse zone could be found to place record for $address pointing to $name";
		$alert->class = 'warning';
		$active_user->add_alert($alert);
		$revs_missing[$type][] = array('name' => $name, 'address' => $address);
		return false;
	}

	/**
	* Given a DNS name, remove the bottom-level subdomain from it.
	* @param string $address DNS name
	* @return bool true if any subdomain could be removed
	*/
	private function remove_subdomain(&$address) {
		$dotpos = strpos($address, '.');
		if($dotpos === false) return false;
		$address = substr($address, $dotpos + 1);
		return true;
	}

	/**
	* Export the listed zones to bind9 and add/commit to the git-tracked export
	* @param array $zones to be exported and committed
	* @param string $message commit message
	*/
	public function git_tracked_export(array $zones, $message) {
		global $config, $active_user;
		if($config['git_tracked_export']['enabled']) {
			$original_dir = getcwd();
			if(chdir($config['git_tracked_export']['path'])) {
				foreach($zones as $zone) {
					$bind9_output = $zone->export_as_bind9_format();
					$outfile = urlencode(DNSZoneName::unqualify($zone->name));
					$fh = fopen($outfile, 'w');
					fwrite($fh, $bind9_output);
					fclose($fh);
					exec('LANG=en_US.UTF-8 git add '.escapeshellarg($outfile));
				}
				exec('LANG=en_US.UTF-8 git commit --author '.escapeshellarg($active_user->name.' <'.$active_user->email.'>').' -m '.escapeshellarg($message));
			}
			chdir($original_dir);
		}
	}

	/**
	* Remove the specified zone from the git-tracked export
	* @param Zone $zone to be removed
	* @param string $message commit message
	*/
	public function git_tracked_delete(Zone $zone, $message) {
		global $config, $active_user;
		if($config['git_tracked_export']['enabled']) {
			$original_dir = getcwd();
			if(chdir($config['git_tracked_export']['path'])) {
				$outfile = urlencode(DNSZoneName::unqualify($zone->name));
				exec('LANG=en_US.UTF-8 git rm '.escapeshellarg($outfile));
				exec('LANG=en_US.UTF-8 git commit --author '.escapeshellarg($active_user->name.' <'.$active_user->email.'>').' -m '.escapeshellarg($message));
			}
			chdir($original_dir);
		}
	}
}

class ZoneNotFound extends RuntimeException {};
