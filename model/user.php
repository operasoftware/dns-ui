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

class User extends Record {
	protected $table = 'user';
	private $ldap;
	private $group_cache = null;

	public function __construct($id = null, $preload_data = array()) {
		parent::__construct($id, $preload_data);
		global $ldap;
		$this->ldap = $ldap;
	}

	public function add_alert(UserAlert $alert) {
		if(is_null($this->id)) throw new BadMethodCallException('User must be in directory before alerts can be added');
		$stmt = $this->database->prepare('INSERT INTO user_alert (user_id, class, content, escaping) VALUES (?, ?, ?, ?)');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->bindParam(2, $alert->class, PDO::PARAM_STR);
		$stmt->bindParam(3, $alert->content, PDO::PARAM_STR);
		$stmt->bindParam(4, $alert->escaping, PDO::PARAM_INT);
		$stmt->execute();
		$alert->id = $this->database->lastInsertId('user_alert_id_seq');
	}

	public function list_alerts() {
		if(is_null($this->id)) throw new BadMethodCallException('User must be in directory before alerts can be listed');
		$stmt = $this->database->prepare('SELECT * FROM user_alert WHERE user_id = ?');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->execute();
		$alerts = array();
		$alert_ids = array();
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$alerts[] = new UserAlert($row['id'], $row);
			$alert_ids[] = $row['id'];
		}
		if(count($alert_ids) > 0) {
			$this->database->query('DELETE FROM user_alert WHERE id IN ('.implode(', ', $alert_ids).')');
		}
		return $alerts;
	}

	public function get_csrf_field() {
		return '<input type="hidden" name="csrf_token" value="'.hesc($this->get_csrf_token()).'"><!-- '.hash("sha512", mt_rand(0, mt_getrandmax())).' -->'."\n";
	}

	public function get_csrf_token() {
		if(is_null($this->id)) throw new BadMethodCallException('User must be in directory before CSRF token can be generated');
		if(!isset($this->data['csrf_token'])) {
			$this->data['csrf_token'] = hash("sha512", mt_rand(0, mt_getrandmax()));
			$this->update();
		}
		return $this->data['csrf_token'];
	}

	public function check_csrf_token($token) {
		return $token == $this->get_csrf_token();
	}

	public function get_details_from_ldap() {
		global $config;
		$attributes = array();
		$attributes[] = 'dn';
		$attributes[] = $config['ldap']['user_id'];
		$attributes[] = $config['ldap']['user_name'];
		$attributes[] = $config['ldap']['user_email'];
		$attributes[] = $config['ldap']['group_member_value'];
		if(isset($config['ldap']['user_active'])) {
			$attributes[] = $config['ldap']['user_active'];
		}
		$ldapusers = $this->ldap->search($config['ldap']['dn_user'], LDAP::escape($config['ldap']['user_id']).'='.LDAP::escape($this->uid), array_keys(array_flip($attributes)));
		if($ldapuser = reset($ldapusers)) {
			$this->auth_realm = 'LDAP';
			$this->uid = $ldapuser[strtolower($config['ldap']['user_id'])];
			$this->name = $ldapuser[strtolower($config['ldap']['user_name'])];
			$this->email = $ldapuser[strtolower($config['ldap']['user_email'])];
			if(isset($config['ldap']['user_active'])) {
				$this->active = 0;
				if(isset($config['ldap']['user_active_true'])) {
					$this->active = intval($ldapuser[strtolower($config['ldap']['user_active'])] == $config['ldap']['user_active_true']);
				} elseif(isset($config['ldap']['user_active_false'])) {
					$this->active = intval($ldapuser[strtolower($config['ldap']['user_active'])] != $config['ldap']['user_active_false']);
				}
			} else {
				$this->active = 1;
			}
			$this->admin = 0;
			$group_member = $ldapuser[strtolower($config['ldap']['group_member_value'])];
			$ldapgroups = $this->ldap->search($config['ldap']['dn_group'], LDAP::escape($config['ldap']['group_member']).'='.LDAP::escape($group_member), array('cn'));
			foreach($ldapgroups as $ldapgroup) {
				if($ldapgroup['cn'] == $config['ldap']['admin_group_cn']) $this->admin = 1;
			}
		} else {
			throw new UserNotFoundException('User does not exist.');
		}
	}

	public function access_to(Zone $zone) {
		$stmt = $this->database->prepare('SELECT level FROM zone_access WHERE user_id = ? AND zone_id = ?');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->bindParam(2, $zone->id, PDO::PARAM_INT);
		$stmt->execute();
		if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			return $row['level'];
		} else {
			return false;
		}
	}

	public function list_admined_zones() {
		global $zone_dir;
		$zones = $zone_dir->list_zones();
		$admined_zones = array();
		foreach($zones as $zone) {
			if($this->access_to($zone)) $admined_zones[$zone->pdns_id] = $zone;
		}
		return $admined_zones;
	}

	public function list_accessible_zones() {
		global $zone_dir;
		if($this->admin) {
			$zones = $zone_dir->list_zones();
		} else {
			$zones = $this->list_admined_zones();
		}
		return $zones;
	}

	public function list_changesets() {
		global $user_dir;
		$stmt = $this->database->prepare('
		SELECT changeset.*, zone.pdns_id, zone.name, zone.serial, zone.account, zone.active
		FROM changeset
		INNER JOIN zone ON zone.id = changeset.zone_id
		WHERE author_id = ?
		ORDER BY id DESC
		');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->execute();
		$changesets = array();
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$row['author'] = $this;
			$row['requester'] = (is_null($row['requester_id']) ? null : $user_dir->get_user_by_id($row['requester_id']));
			$row['zone'] = new Zone($row['id'], array('pdns_id' => $row['pdns_id'], 'name' => $row['name'], 'serial' => $row['serial'], 'account' => $row['account'], 'active' => $row['active']));
			unset($row['pdns_id']);
			unset($row['name']);
			unset($row['serial']);
			unset($row['account']);
			unset($row['active']);
			$row['change_date'] = DateTime::createFromFormat('Y-m-d H:i:s.u', $row['change_date']);
			$changesets[] = new ChangeSet($row['id'], $row);
		}
		return $changesets;
	}
}
