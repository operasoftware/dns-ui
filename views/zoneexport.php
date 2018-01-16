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

try {
	$zone = $zone_dir->get_zone_by_name($router->vars['name'].'.');
} catch(ZoneNotFound $e) {
	require('views/error404.php');
	exit;
}

if(!$active_user->admin && !$active_user->access_to($zone)) {
	require('views/error403.php');
	exit;
}

$rrsets = $zone->list_resource_record_sets();

$page = new PageSection('zoneexport');
$page->set('zone', $zone);
$page->set('rrsets', $rrsets);
header('Content-type: text/plain; charset=utf-8');
header('Content-disposition: attachment; filename='.DNSZoneName::unqualify($zone->name));
echo $page->generate();
