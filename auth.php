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

function is_form_authenticated() {
	global $config;
	if ($config['authentication']['form_based'] == "ldap") {
		if (isset($_SESSION['loggedin']) && $_SESSION['loggedin']) {
			return true;
		}
	}
	return false;
}

if ($config['authentication']['form_based'] == "ldap") {
	session_start();
	if (! isset($_SESSION['loggedin']) || ! $_SESSION['loggedin']) {
		$_SESSION['loggedin'] = false;

		if (!empty($_POST) && $relative_request_url == '/login' ) {
			if (isset($_POST['username']) && isset($_POST['password'])) {
				if ($ldap->auth($_POST['username'], $_POST['password'], 
						$config['ldap']['user_id'], $config['ldap']['dn_user'])) {
					$_SESSION['loggedin'] = true;
					$_SESSION['user'] = $_POST['username'];
					require('views/home.php');
					die;
				} else {
					error_log("Failed login attempt for user '" . $_POST['username'] . "'"); 
				}
			}
		}

		if (! $_SESSION['loggedin']) {
			require('views/login.php');
			die;
		}
	}
	if (isset($_SESSION['loggedin']) && $_SESSION['loggedin']) {
		if ($relative_request_url == '/logout' ) {
			$_SESSION['loggedin'] = false;
			$_SESSION['user'] = null;
			require('views/home.php');
			die;
		}

		$active_user = $user_dir->get_user_by_uid($_SESSION['user']);
	}
}

