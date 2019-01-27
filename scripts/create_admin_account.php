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
require('../core.php');

echo "This script creates a new admin account in DNS UI.\n";
echo "If you are using a central authentication directory (eg. LDAP) then\n";
echo "you probably don't need to use this.\n";

echo "\nUser ID of new admin account:\n";
$uid = trim(fgets(STDIN));
echo "\nFull name of user:\n";
$name = trim(fgets(STDIN));
echo "\nEmail address of user:\n";
$email = trim(fgets(STDIN));

$user = new User;
$user->auth_realm = 'local';
$user->uid = $uid;
$user->name = $name;
$user->email = $email;
$user->active = 1;
$user->admin = 1;
try {
	$user_dir->add_user($user);
	echo "\nAdministrative user $uid created.\n";
} catch(UserAlreadyExistsException $e) {
	echo "\nA user with user ID of $uid already exists.\n";
}
