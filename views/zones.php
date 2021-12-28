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

$zones = $active_user->list_accessible_zones(array('pending_updates'));
usort($zones, function($a, $b) {
	$aname = implode(',', array_reverse(explode('.', punycode_to_utf8($a->name))));
	$bname = implode(',', array_reverse(explode('.', punycode_to_utf8($b->name))));
	return strnatcasecmp($aname, $bname);
});

$replication_types = $replication_type_dir->list_replication_types();
$soa_templates = $template_dir->list_soa_templates();
$ns_templates = $template_dir->list_ns_templates();
$account_whitelist = !empty($config['dns']['classification_whitelist']) ? explode(',', $config['dns']['classification_whitelist']) : [];
$force_account_whitelist = !empty($config['dns']['classification_whitelist']) ? 1 : 0;

if($_SERVER['REQUEST_METHOD'] == 'POST') {
	if(isset($_POST['add_zone']) && $active_user->admin) {
		$zonename = utf8_to_punycode(rtrim(trim($_POST['name']), '.')).'.';
		if(strlen($zonename) == 1) {
			$content = new PageSection('zone_add_failed');
			$content->set('message', 'No zone name given.');
		} else {
			$zone = new Zone;
			$zone->name = $zonename;
			$zone->account = trim($_POST['classification']);
			$zone->dnssec = isset($_POST['dnssec']) ? 1 : 0;
			$zone->kind = $_POST['kind'];
			$zone->nameservers = array();
			foreach(preg_split('/[,\s]+/', $_POST['nameservers']) as $nameserver) {
				$zone->nameservers[] = $nameserver;
			}
			$soa = new ResourceRecord;
			$soa->content = "$_POST[primary_ns] $_POST[contact] ".date('Ymd00')." ".DNSTime::expand($_POST['refresh'])." ".DNSTime::expand($_POST['retry'])." ".DNSTime::expand($_POST['expire'])." ".DNSTime::expand($_POST['default_ttl']);
			$soa->disabled = false;
			$soaset = new ResourceRecordSet;
			$soaset->name = $zonename;
			$soaset->type = 'SOA';
			$soaset->ttl = DNSTime::expand($_POST['soa_ttl']);
			$soaset->add_resource_record($soa);
			$zone->add_resource_record_set($soaset);
			try {
				$zone_dir->create_zone($zone);
				redirect('/zones/'.urlencode(DNSZoneName::unqualify($zonename)));
			} catch(Pest_InvalidRecord $e) {
				$content = new PageSection('zone_add_failed');
				$content->set('message', json_decode($e->getMessage())->error);
			}
		}
	}
}

if(!isset($content)) {
	$content = new PageSection('zones');
	$content->set('zones', $zones);
	$content->set('replication_types', $replication_types);
	$content->set('soa_templates', $soa_templates);
	$content->set('ns_templates', $ns_templates);
	$content->set('dnssec_enabled', isset($config['dns']['dnssec']) ? $config['dns']['dnssec'] : '0');
	$content->set('dnssec_edit', isset($config['dns']['dnssec_edit']) ? $config['dns']['dnssec_edit'] : '0');
	$content->set('account_whitelist', $account_whitelist);
	$content->set('force_account_whitelist', $force_account_whitelist);
}

$page = new PageSection('base');
$page->set('title', 'Zones');
$page->set('content', $content);
$page->set('alerts', $active_user->pop_alerts());

echo $page->generate();
