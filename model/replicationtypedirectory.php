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
* Class for reading from the list of Replication Type objects in the database.
*/
class ReplicationTypeDirectory extends DBDirectory {
	/**
	* List all replication types in the database.
	* @return array of ReplicationType objects
	*/
	public function list_replication_types() {
		$stmt = $this->database->prepare('
			SELECT replication_type.*, CASE WHEN config.id IS NULL THEN 0 ELSE 1 END AS default
			FROM replication_type
			LEFT JOIN config ON config.default_replication_type = replication_type.id
			ORDER BY name
		');
		$stmt->execute();
		$repltypes = array();
		while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			$repltypes[] = new ReplicationType($row['id'], $row);
		}
		return $repltypes;
	}

	/**
	* Get a ReplicationType from the database by its id.
	* @param int $id of template
	* @return ReplicationType with specified id
	* @throws ReplicationTypeNotFound if no ReplicationType with that id exists
	*/
	public function get_replication_type_by_id($id) {
		$stmt = $this->database->prepare('
			SELECT replication_type.*, CASE WHEN config.id IS NULL THEN 0 ELSE 1 END AS default
			FROM replication_type
			LEFT JOIN config ON config.default_replication_type = replication_type.id
			WHERE replication_type.id = ?
		');
		$stmt->bindParam(1, $id, PDO::PARAM_INT);
		$stmt->execute();
		if($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			return new ReplicationType($row['id'], $row);
		} else {
			throw new ReplicationTypeNotFound;
		}
	}

	/**
	* Set the provided replication type as the default for new zones.
	* @param ReplicationType $type to be set as default
	*/
	public function set_default_replication_type(ReplicationType $type = null) {
		$stmt = $this->database->prepare('UPDATE config SET default_replication_type = ?');
		if(is_null($type)) {
			$stmt->bindParam(1, $type, PDO::PARAM_INT);
		} else {
			$stmt->bindParam(1, $type->id, PDO::PARAM_INT);
		}
		$stmt->execute();
	}
}

class ReplicationTypeNotFound extends RuntimeException {}
