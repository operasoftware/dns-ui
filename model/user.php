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
* Class that represents a user of this system.
*/
class User extends Record {
	/**
	* Defines the database table that this object is stored in
	*/
	protected $table = 'user';
	/**
	* LDAP connection object
	*/
	private $ldap;

	public function __construct($id = null, $preload_data = array()) {
		parent::__construct($id, $preload_data);
		global $ldap;
		$this->ldap = $ldap;
	}

	/**
	* Add an alert to be displayed to this user on their next normal page load.
	* @param UserAlert $alert to be displayed
	*/
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

	/**
	* List all alerts for this user *and* delete them.
	* @return array of UserAlert objects
	*/
	public function pop_alerts() {
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

	/**
	* Return HTML containing this user's CSRF token for inclusion in a POST form.
	* Also includes a random string of the same length to help guard against http://breachattack.com/
	* @return string HTML
	*/
	public function get_csrf_field() {
		return '<input type="hidden" name="csrf_token" value="'.hesc($this->get_csrf_token()).'"><!-- '.hash("sha512", mt_rand(0, mt_getrandmax())).' -->'."\n";
	}

	/**
	* Return this user's CSRF token. Generate one if they do not yet have one.
	* @return string CSRF token
	*/
	public function get_csrf_token() {
		if(is_null($this->id)) throw new BadMethodCallException('User must be in directory before CSRF token can be generated');
		if(!isset($this->data['csrf_token'])) {
			$this->data['csrf_token'] = hash("sha512", mt_rand(0, mt_getrandmax()));
			$this->update();
		}
		return $this->data['csrf_token'];
	}

	/**
	* Check the given string against this user's CSRF token.
	* @return bool true on string match
	*/
	public function check_csrf_token($token) {
		return $token === $this->get_csrf_token();
	}

	/**
	* Retrieve this user's details from the configured data source.
	* @throws UserDataSourceException if no user data source is configured
	*/
	public function get_details() {
		global $config;
		if(!empty($config['ldap']['enabled'])) {
			$this->get_details_from_ldap();
		} elseif(!empty($config['php_auth']['enabled'])) {
			$this->get_details_from_php_auth();
		} else {
			throw new UserDataSourceException('User data source not configured.');
		}
	}

	/**
	* Retrieve this user's details from PHP_AUTH variables.
	* @throws UserNotFoundException if the user details are not found in PHP_AUTH variables
	*/
	public function get_details_from_php_auth() {
		global $config;
		if($this->uid == $_SERVER['PHP_AUTH_USER'] and isset($_SERVER['PHP_AUTH_NAME']) and isset($_SERVER['PHP_AUTH_EMAIL']) and isset($_SERVER['PHP_AUTH_GROUPS'])) {
			$this->auth_realm = 'PHP_AUTH';
			$this->name = $_SERVER['PHP_AUTH_NAME'];
			$this->email = $_SERVER['PHP_AUTH_EMAIL'];
			$this->active = 1;
			$this->admin = 0;
			$groups = explode(' ', $_SERVER['PHP_AUTH_GROUPS']);
			foreach($groups as $group) {
				if($group == $config['php_auth']['admin_group']) $this->admin = 1;
			}
		} else {
			throw new UserNotFoundException('User does not exist in PHP_AUTH variables.');
		}
	}

	/**
	* Retrieve this user's details from LDAP.
	* @throws UserNotFoundException if the user is not found in LDAP
	*/
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

		$filter = sprintf("(%s=%s)", LDAP::escape($config['ldap']['user_id']), LDAP::escape($this->uid));
		if ( isset($config['ldap']['extra_user_filter']) ) {
			$filter = sprintf("(&%s%s)", $config['ldap']['extra_user_filter'], $filter);
		}

		$ldapusers = $this->ldap->search($config['ldap']['dn_user'], $filter, array_keys(array_flip($attributes)));
		if($ldapuser = reset($ldapusers)) {
			$this->auth_realm = 'LDAP';

			foreach (array('user_id', 'user_name', 'user_email') as $key) {
				if (!isset($ldapuser[strtolower($config['ldap'][$key])])) {
					throw new UserNotFoundException(sprintf('User misses %s attribute in LDAP directory.', $config['ldap'][$key]));
				}
			}
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
			throw new UserNotFoundException('User does not exist in LDAP.');
		}
	}

	/**
	* Return the access level of this user to the specified zone.
	* @param Zone $zone to check for access
	* @return string name of access level
	*/
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

	/**
	* List all zones that this user is an administrator of
	* @param array $include list of extra data to include in response
	* @return array of Zone objects
	*/
	public function list_admined_zones($include = array()) {
		global $zone_dir;
		$zones = $zone_dir->list_zones($include);
		$admined_zones = array();
		foreach($zones as $zone) {
			if($this->access_to($zone)) $admined_zones[$zone->pdns_id] = $zone;
		}
		return $admined_zones;
	}

	/**
	* List all zones that this user has access to in some way
	* @param array $include list of extra data to include in response
	* @return array of Zone objects
	*/
	public function list_accessible_zones($include = array()) {
		global $zone_dir;
		if($this->admin) {
			$zones = $zone_dir->list_zones($include);
		} else {
			$zones = $this->list_admined_zones($include);
		}
		return $zones;
	}

	/**
	* List all changes that this user has made to any zones
	* @return array of ChangeSet objects
	*/
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
			$row['change_date'] = parse_postgres_date($row['change_date']);
			$changesets[] = new ChangeSet($row['id'], $row);
		}
		return $changesets;
	}
}
