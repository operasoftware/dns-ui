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

/**
* Class that represents a record within a zone recordset.
* Not stored in the local database.
*/
class ResourceRecord {
	/**
	* Store object-related data
	*/
	private $data;

	/**
	* Create the object based on data from PowerDNS (if provided).
	* @param StdClass|null $data from PowerDNS
	*/
	public function __construct($data = null) {
		if(is_null($data)) $data = new StdClass;
		$this->data = $data;
	}

	/**
	* Magic setter method - store data in local data object.
	* @param string $field to update
	* @param mixed $value to store in field
	*/
	public function __set($field, $value) {
		$this->data->{$field} = $value;
	}

	/**
	* Magic getter method - retrieve data from local data object.
	* @param string $field to retrieve
	* @return mixed data stored in field
	*/
	public function __get($field) {
		if(isset($this->data->{$field})) return $this->data->{$field};
		return null;
	}
}

class ResourceRecordInvalid extends Exception {}
