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

function dns_ui_start_session() {
	global $config;

	$options = array();
	$options['use_strict_mode'] = true;

	if (isset($config['session'])) {
		// allow to set some session options from configuration
		$whitelisted = array('name', 'cookie_path', 'cookie_lifetime', 'cookie_secure');

		foreach($config['session'] as $k => $v) {
			if (array_search($k, $whitelisted) !== FALSE) {
				$options[$k] = $v;
			}
		}
	}
	session_start($options);
}

function auth_by_ldap($user, $pass) {
	global $config;
	global $ldap;

	if ( ! $config['ldap']['enabled']) {
		error_log("Use of LDAP must be enabled to use LDAP form-based authentication");
		throw new Exception('Misconfiguration detected - check the error log');
	}

	return $ldap->auth($user, $pass, 
			   $config['ldap']['user_id'], $config['ldap']['dn_user'],
			   isset($config['ldap']['extra_user_filter']) 
				? $config['ldap']['extra_user_filter']
				: null
			   );
}

if ($config['authentication']['form_based'] !== false) {
	dns_ui_start_session();
	if (! isset($_SESSION['loggedin']) || ! $_SESSION['loggedin']) {
		$_SESSION['loggedin'] = false;

		if (!empty($_POST) && $relative_request_url == '/login' ) {
			if (isset($_POST['username']) && isset($_POST['password'])) {
				$authed = false;

				try {
					// other authentication methods could be implemented here...
					if ($config['authentication']['form_based'] == "ldap") {
						$authed = auth_by_ldap($_POST['username'], $_POST['password']);
					}

					if ($authed) {
						// OK, authenticated - but can we get user details???
						// if we can't this will throw an exception...
						$active_user = $user_dir->get_user_by_uid($_POST['username']);

						if(!$active_user->active) {
							// user is no longer active. Behave as if login failed
							error_log("Login attempt by inactive user '" . $_POST['username'] . "'");
						} else {
							$_SESSION['loggedin'] = true;
							$_SESSION['user'] = $_POST['username'];
							require('views/home.php');
							die;
						}
					} else {
						error_log("Failed login attempt for user '" . $_POST['username'] . "'"); 
					}
				} catch (Exception $e) {
					$_SESSION['loggedin'] = false;
					$_SESSION['user'] = null;

					error_log($e);
					$alert = new UserAlert;
					$alert->content = sprintf('Login failed: %s', $e->getMessage());
					$login_alerts = array($alert);

					require('views/login.php');
					die;
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
