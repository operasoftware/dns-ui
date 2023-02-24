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

class LDAP {
	private $conn;
	private $host;
	private $starttls;
	private $bind_dn;
	private $bind_password;
	private $options;

	public function __construct($host, $starttls, $bind_dn, $bind_password, $options) {
		$this->conn = null;
		$this->host = $host;
		$this->starttls = $starttls;
		$this->bind_dn = $bind_dn;
		$this->bind_password = $bind_password;
		$this->options = $options;
	}

	private function connect() {
		$this->conn = ldap_connect($this->host);
		if($this->conn === false) throw new LDAPConnectionFailureException('Invalid LDAP connection settings');
		if($this->starttls) {
			if(!ldap_start_tls($this->conn)) throw new LDAPConnectionFailureException('Could not initiate TLS connection to LDAP server');
		}
		foreach($this->options as $option => $value) {
			ldap_set_option($this->conn, $option, $value);
		}
		if(!empty($this->bind_dn)) {
			if(!ldap_bind($this->conn, $this->bind_dn, $this->bind_password)) throw new LDAPConnectionFailureException('Could not bind to LDAP server');
		}
	}

	public function auth($uid, $pass, $user_id_attr, $basedn, $extrafilter) {
		if(is_null($this->conn)) $this->connect();

		$filter = sprintf("(%s=%s)", LDAP::escape($user_id_attr), LDAP::escape($uid));
		if ( isset($extrafilter) ) {
			$filter = sprintf("(&%s%s)", $extrafilter, $filter);
		}

		$r = @ldap_search($this->conn, $basedn, $filter);

		if(! $r) {
			return false;
		}

		// Fetch entries
		$result = @ldap_get_entries($this->conn, $r);

		if ($result['count'] != 1) {
			return false;
		}

		$authdn = $result[0]['dn'];
		
		$authconn = ldap_connect($this->host);
		if($authconn === false) throw new LDAPConnectionFailureException('Invalid LDAP connection settings');
		if($this->starttls) {
			if(!ldap_start_tls($authconn)) throw new LDAPConnectionFailureException('Could not initiate TLS connection to LDAP server');
		}
		foreach($this->options as $option => $value) {
			ldap_set_option($authconn, $option, $value);
		}

		try {
			$bound = @ldap_bind($authconn, $authdn, $pass);
			return $bound;
		} catch (Exception $e) {
			return false;
		} finally {
			@ldap_unbind($authconn);
		}
	}

	public function search($basedn, $filter, $fields = array(), $sort = array()) {
		if(is_null($this->conn)) $this->connect();
		if(empty($fields)) $r = @ldap_search($this->conn, $basedn, $filter);
		else $r = @ldap_search($this->conn, $basedn, $filter, $fields);
		$sort = array_reverse($sort);
		foreach($sort as $field) {
			@ldap_sort($this->conn, $r, $field);
		}
		if($r) {
			// Fetch entries
			$result = @ldap_get_entries($this->conn, $r);
			unset($result['count']);
			$items = array();
			foreach($result as $item) {
				unset($item['count']);
				$itemResult = array();
				foreach($item as $key => $values) {
					if(!is_int($key)) {
						if(is_array($values)) {
							unset($values['count']);
							if(count($values) == 1) $values = $values[0];
						}
						$itemResult[$key] = $values;
					}
				}
				$items[] = $itemResult;
			}
			return $items;
		}
		return false;
	}

	public static function escape($str = '') {
		$metaChars = array("\\00", "\\", "(", ")", "*");
		$quotedMetaChars = array();
		foreach($metaChars as $key => $value) {
			$quotedMetaChars[$key] = '\\'. dechex(ord($value));
		}
		$str = str_replace($metaChars, $quotedMetaChars, $str);
		return $str;
	}
}

class LDAPConnectionFailureException extends RuntimeException {}
