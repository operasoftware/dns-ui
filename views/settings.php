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

$replication_types = $replication_type_dir->list_replication_types();
$ns_templates = $template_dir->list_ns_templates();
$soa_templates = $template_dir->list_soa_templates();

if($_SERVER['REQUEST_METHOD'] == 'POST') {
	if(isset($_POST['update_settings'])) {
		if($_POST['default_replication_type'] === '') {
			$type = null;
		} else {
			$type = $replication_type_dir->get_replication_type_by_id($_POST['default_replication_type']);
		}
		$replication_type_dir->set_default_replication_type($type);

		if($_POST['default_soa_template'] === '') {
			$template = null;
		} else {
			$template = $template_dir->get_soa_template_by_id($_POST['default_soa_template']);
		}
		$template_dir->set_default_soa_template($template);

		if($_POST['default_ns_template'] === '') {
			$template = null;
		} else {
			$template = $template_dir->get_ns_template_by_id($_POST['default_ns_template']);
		}
		$template_dir->set_default_ns_template($template);

		$alert = new UserAlert;
		$alert->content = "Settings updated.";
		$active_user->add_alert($alert);

		redirect();
	}
}

$content = new PageSection('settings');
$content->set('replication_types', $replication_types);
$content->set('ns_templates', $ns_templates);
$content->set('soa_templates', $soa_templates);

$page = new PageSection('base');
$page->set('title', 'Settings');
$page->set('content', $content);
$page->set('alerts', $active_user->pop_alerts());

echo $page->generate();
