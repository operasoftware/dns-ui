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
* Class that represents a set of changes that have been made to a zone.
*/
class ChangeSet extends Record {
	/**
	* Defines the database table that this object is stored in
	*/
	protected $table = 'changeset';

	/**
	* Add an individual change to this changeset
	* @param Change $change to add
	*/
	public function add_change(Change $change) {
		global $active_user;
		$before = $change->before;
		$after = $change->after;
		$deleted = (int)!is_null($before);
		$added = (int)!is_null($after);
		$stmt = $this->database->prepare('INSERT INTO "change" (changeset_id, before, after) VALUES (?, ?, ?)');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->bindParam(2, $before, PDO::PARAM_LOB);
		$stmt->bindParam(3, $after, PDO::PARAM_LOB);
		$stmt->execute();
		$change->id = $this->database->lastInsertId('change_id_seq');
		$stmt = $this->database->prepare('UPDATE "changeset" SET added = added + ?, deleted = deleted + ? WHERE id = ?');
		$stmt->bindParam(1, $added, PDO::PARAM_INT);
		$stmt->bindParam(2, $deleted, PDO::PARAM_INT);
		$stmt->bindParam(3, $this->id, PDO::PARAM_INT);
		$stmt->execute();
	}

	/**
	* List all changes in this changeset
	* @return array of Change objects
	*/
	public function list_changes() {
		$stmt = $this->database->prepare('SELECT * FROM "change" WHERE changeset_id = ?');
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->execute();
		$changes = array();
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			if(!is_null($row['before'])) $row['before'] = stream_get_contents($row['before']);
			if(!is_null($row['after'])) $row['after'] = stream_get_contents($row['after']);
			$changes[] = new Change($row['id'], $row);
		}
		return $changes;
	}
}
