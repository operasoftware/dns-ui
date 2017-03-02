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

if(!$active_user->admin) {
	require('views/error403.php');
	die;
}

$type = $router->vars['type'];
$name = $router->vars['name'];

try {
	if($type == 'soa') {
		$template = $template_dir->get_soa_template_by_name($name);
	} elseif($type == 'ns') {
		$template = $template_dir->get_ns_template_by_name($name);
	} else {
		require('views/error404.php');
		die;
	}
} catch(TemplateNotFound $e) {
	require('views/error404.php');
	die;
}
if($_SERVER['REQUEST_METHOD'] == 'POST') {
	if(isset($_POST['update_template'])) {
		$template->name = trim($_POST['name']);
		if($type == 'soa') {
			$template->primary_ns = $_POST['primary_ns'];
			$template->contact = $_POST['contact'];
			$template->refresh = DNSTime::expand($_POST['refresh']);
			$template->retry = DNSTime::expand($_POST['retry']);
			$template->expire = DNSTime::expand($_POST['expire']);
			$template->default_ttl = DNSTime::expand($_POST['default_ttl']);
			$template->soa_ttl = DNSTime::expand($_POST['soa_ttl']);
		} elseif($type == 'ns') {
			$template->nameservers = implode("\n", preg_split('/[,\s]+/', trim($_POST['nameservers'])));
		}
		$template->update();
		$alert = new UserAlert;
		$alert->content = "Template updated.";
		$active_user->add_alert($alert);
	}
	redirect('/templates/'.urlencode($type).'/'.urlencode($template->name));
}

$content = new PageSection('template');
$content->set('type', $type);
$content->set('template', $template);

$page = new PageSection('base');
$page->set('title', strtoupper($type).' template: '.$name);
$page->set('content', $content);
$page->set('alerts', $active_user->pop_alerts());

echo $page->generate();
