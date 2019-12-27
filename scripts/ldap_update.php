#!/usr/bin/php
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

chdir(__DIR__);
define('BASE_PATH', dirname(__DIR__));
require('../core.php');

$users = $user_dir->list_users();

foreach($users as $user) {
	if($user->auth_realm == 'LDAP') {
		try {
			$user->get_details_from_ldap();
			$user->update();
		} catch(UserNotFoundException $e) {
			$user->active = 0;
			$user->update();
		}
	}
}
