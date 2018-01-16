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

$routes = array(
	'/' => 'home',
	'/api/v2' => 'api',
	'/api/v2/{objects}' => 'api',
	'/api/v2/{objects}/{id}' => 'api',
	'/api/v2/{objects}/{id}/{subobjects}' => 'api',
	'/api/v2/{objects}/{id}/{subobjects}/{subid}' => 'api',
	'/settings' => 'settings',
	'/templates' => 'templates',
	'/templates/{type}' => 'templates',
	'/templates/{type}/{name}' => 'template',
	'/users' => 'users',
	'/users/{uid}' => 'user',
	'/zones' => 'zones',
	'/zones/{name}' => 'zone',
	'/zones/{name}/import' => 'zoneimport',
	'/zones/{name}/export' => 'zoneexport',
	'/zones/{name}/split' => 'zonesplit',
);
