#!/usr/bin/php
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

chdir(__DIR__);
require('../core.php');

// Use 'import' user as the active user (create if it does not yet exist)
try {
	$active_user = $user_dir->get_user_by_uid('import');
} catch(UserNotFoundException $e) {
	$active_user = new User;
	$active_user->uid = 'import';
	$active_user->name = 'Import script';
	$active_user->email = null;
	$active_user->auth_realm = 'local';
	$active_user->active = 1;
	$active_user->admin = 1;
	$active_user->developer = 0;
	$user_dir->add_user($active_user);
}

if($config['git_tracked_export']['enabled'] != 1) {
	die('git_tracked_export is not enabled (see config/config.ini)');
}

if(empty($argv[1])) {
	echo "Usage: full_git_tracked_export.php <msg>\n";
	exit(1);
}
$comment = $argv[1];

$zones = $zone_dir->list_zones();
$zone_exports = array();
foreach($zones as $zone) {
	$zone_exports[$zone->name] = $zone->export_as_bind9_format();
}
$original_dir = getcwd();
if(chdir($config['git_tracked_export']['path'])) {
	foreach($zone_exports as $name => $export) {
		$name = DNSZoneName::unqualify($name);
		$fh = fopen($name, 'w');
		fwrite($fh, $export);
		fclose($fh);
	}
	exec('LANG=en_US.UTF-8 git add -A');
	exec('LANG=en_US.UTF-8 git commit --author '.escapeshellarg($active_user->name.' <'.$active_user->email.'>').' -m '.escapeshellarg($comment));
	chdir($original_dir);
}
