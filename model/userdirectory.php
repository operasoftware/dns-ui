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
* Class for reading/writing to the list of User objects in the database.
*/
class UserDirectory extends DBDirectory {
	/**
	* LDAP connection object
	*/
	private $ldap;
	/**
	* Avoid making multiple LDAP lookups on the same person by caching their details here
	*/
	private $cache_uid;

	public function __construct() {
		parent::__construct();
		global $ldap;
		$this->ldap = $ldap;
		$this->cache_uid = array();
	}

	/**
	* Create the new user in the database.
	* @param User $user object to add
	*/
	public function add_user(User $user) {
		$stmt = $this->database->prepare('INSERT INTO "user" (uid, name, email, active, admin, auth_realm) VALUES (?, ?, ?, ?, ?, ?)');
		$stmt->bindParam(1, $user->uid, PDO::PARAM_INT);
		$stmt->bindParam(2, $user->name, PDO::PARAM_STR);
		$stmt->bindParam(3, $user->email, PDO::PARAM_STR);
		$stmt->bindParam(4, $user->active, PDO::PARAM_INT);
		$stmt->bindParam(5, $user->admin, PDO::PARAM_INT);
		$stmt->bindParam(6, $user->auth_realm, PDO::PARAM_INT);
		try {
			$stmt->execute();
			$user->id = $this->database->lastInsertId('user_id_seq');
		} catch(PDOException $e) {
			if($e->getCode() == 23505) throw new UserAlreadyExistsException('A user already exists with uid '.$user->uid);
			throw $e;
		}
	}

	/**
	* Get a user from the database by its ID.
	* @param int $id of user
	* @return User with specified ID
	* @throws UserNotFoundException if no user with that ID exists
	*/
	public function get_user_by_id($id) {
		$stmt = $this->database->prepare('SELECT * FROM "user" WHERE id = ?');
		$stmt->bindParam(1, $id, PDO::PARAM_INT);
		$stmt->execute();
		if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$user = new User($row['id'], $row);
		} else {
			throw new UserNotFoundException('User does not exist.');
		}
		return $user;
	}

	/**
	* Get a user from the database by its uid. If it does not exist in the database, retrieve it
	* from LDAP and store in the database.
	* @param string $uid of user
	* @return User with specified uid
	* @throws UserNotFoundException if no user with that uid exists
	*/
	public function get_user_by_uid($uid) {
		if(isset($this->cache_uid[$uid])) {
			return $this->cache_uid[$uid];
		}
		$stmt = $this->database->prepare('SELECT * FROM "user" WHERE uid = ?');
		$stmt->bindParam(1, $uid, PDO::PARAM_STR);
		$stmt->execute();
		if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$user = new User($row['id'], $row);
			$this->cache_uid[$uid] = $user;
		} else {
			$user = new User;
			$user->uid = $uid;
			$this->cache_uid[$uid] = $user;
			$user->get_details();
			$this->add_user($user);
		}
		return $user;
	}

	/**
	* List all users in the database.
	* @param array $include list of extra data to include in response - currently unused
	* @param array $filter list of field/value pairs to filter results on
	* @return array of User objects
	*/
	public function list_users($include = array(), $filter = array()) {
		// WARNING: The search query is not parameterized - be sure to properly escape all input
		$fields = array('"user".*');
		$joins = array();
		$where = array();
		foreach($filter as $field => $value) {
			if($value) {
				switch($field) {
				case 'uid':
					$where[] = "uid REGEXP ".$this->database->quote($value);
					break;
				}
			}
		}
		$stmt = $this->database->prepare('
			SELECT '.implode(', ', $fields).'
			FROM "user" '.implode(" ", $joins).'
			'.(count($where) == 0 ? '' : 'WHERE ('.implode(') AND (', $where).')').'
			GROUP BY "user".id
			ORDER BY "user".uid
		');
		$stmt->execute();
		$users = array();
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$users[] = new User($row['id'], $row);
		}
		return $users;
	}
}

class UserNotFoundException extends Exception {}
class UserDataSourceException extends Exception {}
class UserAlreadyExistsException extends Exception {}
