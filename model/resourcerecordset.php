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
* Class that represents a recordset within a zone.
* Not stored in the local database.
*/
class ResourceRecordSet {
	/**
	* Store object-related data
	*/
	private $data;
	/**
	* All resource records in this recordset
	*/
	private $rrs = array();
	/**
	* All comments on this recordset
	*/
	private $comments = array();

	/**
	* Create the object based on data from PowerDNS (if provided).
	* @param StdClass|null $data from PowerDNS
	*/
	public function __construct($data = array()) {
		$this->data = $data;
	}

	/**
	* Magic setter method - store data in local data array.
	* @param string $field to update
	* @param mixed $value to store in field
	*/
	public function __set($field, $value) {
		$this->data[$field] = $value;
	}

	/**
	* Magic getter method - retrieve data from local data array.
	* @param string $field to retrieve
	* @return mixed data stored in field
	*/
	public function __get($field) {
		if(isset($this->data[$field])) return $this->data[$field];
		return null;
	}

	/**
	* Add a ResourceRecord to this recordset
	* @param ResourceRecord $rr to add
	*/
	public function add_resource_record(ResourceRecord $rr) {
		$this->rrs[] = $rr;
	}

	/**
	* List all ResourceRecord objects in this recordset
	* @return array of ResourceRecord objects
	*/
	public function &list_resource_records() {
		return $this->rrs;
	}

	/**
	* Empty the list of ResourceRecord objects in this recordset
	*/
	public function clear_resource_records() {
		$this->rrs = array();
	}

	/**
	* Add a Comment to this recordset
	* @param Comment $comment to add
	*/
	public function add_comment(Comment $comment) {
		$this->comments[] = $comment;
	}

	/**
	* List all Comment objects associated with this recordset
	* @return array of Comment objects
	*/
	public function list_comments() {
		return $this->comments;
	}

	/**
	* For legacy purposes when we allowed multiple comments per RRset in the UI.
	* This function joins the text of all associated comments.
	* @return string joined text
	*/
	public function merge_comment_text() {
		$text = '';
		foreach($this->comments as $comment) {
			$text = trim($text.' '.$comment->content);
		}
		return $text;
	}

	/**
	* Empty the list of Comment objects associated in this recordset.
	*/
	public function clear_comments() {
		$this->comments = array();
	}

	/**
	* Rename this recordset to a different name/type.
	* As far as PowerDNS is concerned we will be deleting the old RRset and creating a new one.
	* But for changelog purposes we can show this as a rename.
	* @param string $name new name of the RRset
	* @param string $type new type of the RRset
	*/
	public function rename($name, $type) {
		if($this->data['name'] == $name && $this->data['type'] == $type) return false;
		$this->data['name'] = $name;
		$this->data['type'] = $type;
		return true;
	}
}

class ResourceRecordSetInvalid extends Exception {}
