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

if(!$active_user->admin) {
	require('views/error403.php');
	exit;
}

$type = null;
$title = 'Templates';
if(isset($router->vars['type'])) {
	$type = $router->vars['type'];
	$title = strtoupper($type).' templates';
}

if($_SERVER['REQUEST_METHOD'] == 'POST') {
	if(isset($_POST['create_template'])) {
		if($type == 'soa') {
			$template = new SOATemplate;
			$template->name = trim($_POST['name']);
			$template->primary_ns = $_POST['primary_ns'];
			$template->contact = $_POST['contact'];
			$template->refresh = DNSTime::expand($_POST['refresh']);
			$template->retry = DNSTime::expand($_POST['retry']);
			$template->expire = DNSTime::expand($_POST['expire']);
			$template->default_ttl = DNSTime::expand($_POST['default_ttl']);
			$template->soa_ttl = DNSTime::expand($_POST['soa_ttl']);
		} elseif($type == 'ns') {
			$template = new NSTemplate;
			$template->name = trim($_POST['name']);
			$template->nameservers = implode("\n", preg_split('/[,\s]+/', trim($_POST['nameservers'])));
		}
		$template_dir->add_template($template);
		$alert = new UserAlert;
		$alert->content = "Template created.";
		$active_user->add_alert($alert);
	} elseif(isset($_POST['set_default_soa_template'])) {
		$template = $template_dir->get_soa_template_by_id($_POST['set_default_soa_template']);
		$template_dir->set_default_soa_template($template);
		$alert = new UserAlert;
		$alert->content = "New SOA default set.";
		$active_user->add_alert($alert);
	} elseif(isset($_POST['set_default_ns_template'])) {
		$template = $template_dir->get_ns_template_by_id($_POST['set_default_ns_template']);
		$template_dir->set_default_ns_template($template);
		$alert = new UserAlert;
		$alert->content = "New NS default set.";
		$active_user->add_alert($alert);
	} elseif(isset($_POST['delete_soa_template'])) {
		$template = $template_dir->get_soa_template_by_id($_POST['delete_soa_template']);
		$template_dir->delete_template($template);
		$alert = new UserAlert;
		$alert->content = "SOA template deleted.";
		$active_user->add_alert($alert);
	} elseif(isset($_POST['delete_ns_template'])) {
		$template = $template_dir->get_ns_template_by_id($_POST['delete_ns_template']);
		$template_dir->delete_template($template);
		$alert = new UserAlert;
		$alert->content = "NS template deleted.";
		$active_user->add_alert($alert);
	}
	redirect('/templates/'.urlencode($type).'#list');
}
$soa_templates = $template_dir->list_soa_templates();
$ns_templates = $template_dir->list_ns_templates();

$content = new PageSection('templates');
$content->set('title', $title);
$content->set('soa_templates', $soa_templates);
$content->set('ns_templates', $ns_templates);
$content->set('type', $type);

$page = new PageSection('base');
$page->set('title', $title);
$page->set('content', $content);
$page->set('alerts', $active_user->pop_alerts());

echo $page->generate();
