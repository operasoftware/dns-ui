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

chdir(dirname(__FILE__));
require('core.php');
ob_start();
set_exception_handler('exception_handler');

if(isset($_SERVER['PHP_AUTH_USER'])) {
	$active_user = $user_dir->get_user_by_uid($_SERVER['PHP_AUTH_USER']);
} elseif(isset($_SERVER['REMOTE_USER'])) {
	$active_user = $user_dir->get_user_by_uid($_SERVER['REMOTE_USER']);
} else {
	throw new Exception("Not logged in.");
}

// Work out where we are on the server
$request_url = preg_replace('|(.)/$|', '$1', $_SERVER['REQUEST_URI']);
$relative_request_url = preg_replace('/^'.preg_quote($relative_frontend_base_url, '/').'/', '', $request_url) ?: '/';
$absolute_request_url = $frontend_root_url.$request_url;

if(empty($config['web']['enabled'])) {
	require('views/error503.php');
	die;
}

if(!$active_user->active) {
	require('views/error403.php');
}

if(!empty($_POST)) {
	// Check CSRF token
	if(isset($_SERVER['HTTP_X_BYPASS_CSRF_PROTECTION']) && $_SERVER['HTTP_X_BYPASS_CSRF_PROTECTION'] == 1) {
		// This is being called from script, not a web browser
	} elseif(!$active_user->check_csrf_token($_POST['csrf_token'])) {
		require('views/csrf.php');
		die;
	}
}

// Route request to the correct view
$router = new Router;
foreach($routes as $path => $service) {
	$router->add_route($path, $service);
}
$router->handle_request($relative_request_url);
if(isset($router->view)) {
	$view = path_join($base_path, 'views', $router->view.'.php');
	if(file_exists($view)) {
		require($view);
	} else {
		throw new Exception("View file $view missing.");
	}
}

// Handler for uncaught exceptions
function exception_handler($e) {
	global $active_user;
	if((get_class($e) == 'Pest_Curl_Exec' && $e->getCode() == CURLE_COULDNT_CONNECT) || get_class($e) == 'Pest_Unauthorized') {
		require('views/error503_upstream.php');
		die;
	}
	$error_number = log_exception($e);
	while(ob_get_length()) {
		ob_end_clean();
	}
	require('views/error500.php');
	die;
}
