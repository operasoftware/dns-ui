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

class ResourceRecordSet {
	private $data;
	private $rrs = array();
	private $comments = array();
	private $changes = array();

	public function __construct($data = array()) {
		$this->data = $data;
	}

	public function __set($field, $value) {
		$this->data[$field] = $value;
	}

	public function __get($field) {
		if(isset($this->data[$field])) return $this->data[$field];
		return null;
	}

	public function add_resource_record(ResourceRecord $rr) {
		$this->rrs[] = $rr;
	}

	public function &list_resource_records() {
		return $this->rrs;
	}

	public function clear_resource_records() {
		$this->rrs = array();
	}

	public function add_comment(Comment $comment) {
		$this->comments[] = $comment;
	}

	public function list_comments() {
		return $this->comments;
	}

	public function merge_comment_text() {
		$text = '';
		foreach($this->comments as $comment) {
			$text = trim($text.' '.$comment->content);
		}
		return $text;
	}

	public function clear_comments() {
		$this->comments = array();
	}

	public function rename($name, $type) {
		if($this->data['name'] == $name && $this->data['type'] == $type) return false;
		$this->data['name'] = $name;
		$this->data['type'] = $type;
		return true;
	}
}

class ResourceRecordSetInvalid extends Exception {}
