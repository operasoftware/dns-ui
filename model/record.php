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
* Basic record abstract class. Inherited by most classes whose objects are stored in the database.
* Provides __get, __set and update methods for reading and updating fields.
*/
abstract class Record {
	/**
	* Database connection object
	*/
	protected $database;
	/**
	* User object for the logged-in user
	*/
	protected $active_user;
	/**
	* Set to true if any data in this record has been modified
	*/
	protected $dirty;
	/**
	* The array of data associated with this record
	*/
	protected $data;
	/**
	* Defines the database table that these records are stored in
	*/
	protected $table;
	/**
	* Defines the field that is the primary key of the table
	*/
	protected $idfield = 'id';
	/**
	* The ID of this record
	*/
	public $id;

	public function __construct($id = null, $preload_data = array()) {
		global $database;
		global $active_user;
		$this->database = $database;
		$this->active_user = $active_user;
		$this->id = $id;
		$this->data = array();
		foreach($preload_data as $field => $value) {
			$this->data[$field] = $value;
		}
		if(is_null($this->id)) $this->dirty = true;
	}

	/**
	* Magic getter method - return the value of the specified field. Retrieve the row from the
	* database if we do not have data for that field yet.
	* @param string $field name of field to retrieve
	* @return mixed data stored in field
	* @throws Exception if the row or the field does not exist in the database
	*/
	public function &__get($field) {
		if(!array_key_exists($field, $this->data)) {
			// We don't have a value for this field yet
			if(is_null($this->id)) {
				// Record is not yet in the database - nothing to retrieve
				$result = null;
				return $result;
			}
			// Attempt to get data from database
			$stmt = $this->database->prepare("SELECT * FROM \"$this->table\" WHERE {$this->idfield} = ?");
			$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
			$stmt->execute();

			if($stmt->rowCount() != 1) {
				throw new Exception("Unexpected number of rows returned ({$stmt->num_rows}), expected exactly 1. Table:{$this->table}, ID field: {$this->idfield}, ID: {$this->id}");
			}
			$data = $stmt->fetch(PDO::FETCH_ASSOC);
			// Populate data array for fields we do not already have a value for
			foreach($data as $f => $v) {
				if(!isset($this->data[$f])) {
					$this->data[$f] = $v;
				}
			}
			if(!array_key_exists($field, $this->data)) {
				// We still don't have a value, so this field doesn't exist in the database
				throw new Exception("Field $field does not exist in {$this->table} table.");
			}
		}
		return $this->data[$field];
	}

	/**
	* Magic setter method - store the updated value and set the record as dirty.
	* @param string $field name of field
	* @param mixed $value data to store in field
	*/
	public function __set($field, $value) {
		$this->data[$field] = $value;
		$this->dirty = true;
		if($field == $this->idfield) $this->id = $value;
	}

	/**
	* Update the database with all fields that have been modified.
	* @return array of StdClass detailing actual updates that were applied
	* @throws UniqueKeyViolationException if the update violated a unique key on the table
	*/
	public function update() {
		$stmt = $this->database->prepare("SELECT * FROM \"$this->table\" WHERE {$this->idfield} = ?");
		$stmt->bindParam(1, $this->id, PDO::PARAM_INT);
		$stmt->execute();
		if(!($row = $stmt->fetch(PDO::FETCH_ASSOC))) {
			throw new Exception("Record not found in database");
		}
		$updates = array();
		$fields = array();
		$values = array();
		$types = array();
		foreach($row as $field => $value) {
			if(array_key_exists($field, $this->data) && $this->data[$field] != $value) {
				$update = new StdClass;
				$update->field = $field;
				$update->old_value = $value;
				$update->new_value = $this->data[$field];
				$updates[] = $update;
				$fields[] = "\"$field\" = :$field";
			}
		}
		if(!empty($updates)) {
			try {
				$stmt = $this->database->prepare("UPDATE \"$this->table\" SET ".implode(', ', $fields)." WHERE {$this->idfield} = :id");
				foreach($updates as $update) {
					$stmt->bindParam($update->field, $update->new_value, PDO::PARAM_STR);
				}
				$stmt->bindParam('id', $this->id, PDO::PARAM_INT);
				$stmt->execute();
			} catch(mysqli_sql_exception $e) {
				if($e->getCode() == 1062) {
					// Duplicate entry
					$message = $e->getMessage();
					if(preg_match("/^Duplicate entry '(.*)' for key '(.*)'$/", $message, $matches)) {
						$ne = new UniqueKeyViolationException($e->getMessage());
						$ne->fields = explode(',', $matches[2]);
						$ne->values = explode(',', $matches[1]);
						throw $ne;
					}
				}
				throw $e;
			}
		}
		$this->dirty = false;
		return $updates;
	}
}

class UniqueKeyViolationException extends Exception {
	/**
	* Fields involved in the unique key conflict
	*/
	public $fields;
	/**
	* Values that conflicted
	*/
	public $values;
}
