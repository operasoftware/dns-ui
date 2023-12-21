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

require_once 'PestJSON.php';

class PowerDNS extends PestJSON {
	private $api_key;

	public function __construct($base_url, $api_key) {
		$this->api_key = $api_key;
		parent::__construct($base_url);
	}

    public function get($url, $data = array(), $headers=array()) {
    	$headers['X-API-Key'] = $this->api_key;
        return parent::get($url, $data, $headers);
    }

    public function head($url, $headers = array()) {
    	$headers['X-API-Key'] = $this->api_key;
        return parent::head($url, $headers);
    }

    public function post($url, $data, $headers = array()) {
    	$headers['X-API-Key'] = $this->api_key;
        return parent::post($url, $data, $headers);
    }

    public function put($url, $data, $headers = array()) {
    	$headers['X-API-Key'] = $this->api_key;
        return parent::put($url, $data, $headers);
    }

    public function patch($url, $data, $headers = array()) {
    	$headers['X-API-Key'] = $this->api_key;
        return parent::patch($url, $data, $headers);
    }

    public function delete($url, $headers = array()) {
    	$headers['X-API-Key'] = $this->api_key;
        return parent::delete($url, $headers);
    }
}
