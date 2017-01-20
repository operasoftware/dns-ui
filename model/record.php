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

abstract class Record {
	protected $database;
	protected $active_user;
	protected $dirty;
	protected $schema;
	protected $data;
	protected $table;
	protected $idfield = 'id';
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
				throw new Exception("Unexpected number of rows returned, expected exactly 1.");
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
				throw new Exception("Field $field does not exist.");
			}
		}
		return $this->data[$field];
	}

	public function __set($field, $value) {
		$this->data[$field] = $value;
		$this->dirty = true;
		if($field == $this->idfield) $this->id = $value;
	}

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
	public $fields;
	public $values;
}
