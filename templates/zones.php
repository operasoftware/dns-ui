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
$active_user = $this->get('active_user');
$zones = $this->get('zones');
$soa_templates = $this->get('soa_templates');
$ns_templates = $this->get('ns_templates');
$zone_types = array('forward' => array(), 'reverse4' => array(), 'reverse6' => array());
$accounts = array();
foreach($zones as $zone) {
	if(substr($zone->name, -14) == '.in-addr.arpa.') {
		$zone_types['reverse4'][] = $zone;
	} elseif(substr($zone->name, -10) == '.ip6.arpa.') {
		$zone_types['reverse6'][] = $zone;
	} else {
		$zone_types['forward'][] = $zone;
	}
	if($account = $zone->account) {
		$accounts[$account] = $account;
	}
}
?>
<h1>Zones</h1>
<ul class="nav nav-tabs" role="tablist">
	<li role="presentation" class="active"><a href="#forward" aria-controls="forward" role="tab" data-toggle="tab">Forward zones</a></li>
	<li role="presentation"><a href="#reverse4" aria-controls="reverse4" role="tab" data-toggle="tab">Reverse zones IPv4</a></li>
	<li role="presentation"><a href="#reverse6" aria-controls="reverse6" role="tab" data-toggle="tab">Reverse zones IPv6</a></li>
	<?php if($active_user->admin) { ?>
	<li role="presentation"><a href="#create" aria-controls="create" role="tab" data-toggle="tab">Create zone</a></li>
	<?php } ?>
</ul>
<div class="tab-content">
	<div role="tabpanel" class="tab-pane active" id="forward">
		<h2 class="sr-only">Forward zones</h2>
		<?php if(count($zone_types['forward']) == 0) { ?>
		<p>There are no forward zones defined.</p>
		<?php } else { ?>
		<table class="table table-bordered table-condensed table-hover zonelist">
			<thead>
				<tr>
					<th>Zone name</th>
					<th>Serial</th>
					<th>Classification</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($zone_types['forward'] as $zone) { ?>
				<tr>
					<td class="name"><a href="/zones/<?php out(DNSZoneName::unqualify($zone->name, ESC_URL))?>"><?php out(DNSZoneName::unqualify(punycode_to_utf8($zone->name)))?></a></td>
					<td><?php out($zone->serial)?></td>
					<td><?php out($zone->account)?></td>
				</tr>
				<?php } ?>
			</tbody>
		</table>
		<?php } ?>
	</div>
	<div role="tabpanel" class="tab-pane" id="reverse4">
		<h2 class="sr-only">Reverse zones IPv4</h2>
		<?php if(count($zone_types['reverse4']) == 0) { ?>
		<p>There are no IPv4 reverse zones defined.</p>
		<?php } else { ?>
		<table class="table table-bordered table-condensed table-hover zonelist">
			<thead>
				<tr>
					<th>Zone name</th>
					<th>IPv4 prefix</th>
					<th>Subnet</th>
					<th>Serial</th>
					<th>Classification</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($zone_types['reverse4'] as $zone) { ?>
				<tr>
					<td class="name"><a href="/zones/<?php out(DNSZoneName::unqualify($zone->name), ESC_URL)?>"><?php out(DNSZoneName::unqualify($zone->name))?></a></td>
					<td><?php out(ipv4_reverse_zone_to_range($zone->name))?></td>
					<td><?php out(ipv4_reverse_zone_to_subnet($zone->name))?></td>
					<td><?php out($zone->serial)?></td>
					<td><?php out($zone->account)?></td>
				</tr>
				<?php } ?>
			</tbody>
		</table>
		<?php } ?>
		<?php if($active_user->admin) { ?>
		<div class="form-inline reverse_zone_prefill">
			<label for="ipv4_zone_prefix">IPv4 prefix</label>
			<input type="text" id="ipv4_zone_prefix" class="form-control" pattern="(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])(\.(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])){0,2}\.?" required>
			<button type="button" id="ipv4_zone_create" class="btn btn-primary">Create zone from prefix</button>
		</div>
		<?php } ?>
	</div>
	<div role="tabpanel" class="tab-pane" id="reverse6">
		<h2 class="sr-only">Reverse zones IPv6</h2>
		<?php if(count($zone_types['reverse6']) == 0) { ?>
		<p>There are no IPv6 reverse zones defined.</p>
		<?php } else { ?>
		<table class="table table-bordered table-condensed table-hover zonelist">
			<thead>
				<tr>
					<th>Zone name</th>
					<th>IPv6 prefix</th>
					<th>Subnet</th>
					<th>Serial</th>
					<th>Classification</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($zone_types['reverse6'] as $zone) { ?>
				<tr>
					<td class="name"><a href="/zones/<?php out(DNSZoneName::unqualify($zone->name), ESC_URL)?>"><?php out(DNSZoneName::unqualify($zone->name))?></a></td>
					<td><tt><?php out(ipv6_reverse_zone_to_range($zone->name))?></tt></td>
					<td><?php out(ipv6_reverse_zone_to_subnet($zone->name))?></td>
					<td><?php out($zone->serial)?></td>
					<td><?php out($zone->account)?></td>
				</tr>
				<?php } ?>
			</tbody>
		</table>
		<?php } ?>
		<?php if($active_user->admin) { ?>
		<div class="form-inline reverse_zone_prefill">
			<label for="ipv6_zone_prefix">IPv6 prefix</label>
			<input type="text" id="ipv6_zone_prefix" class="form-control" pattern="([0-9a-fA-F]{1,4})(:[0-9a-fA-F]{1,4}){0,6}\:?" required>
			<button type="button" id="ipv6_zone_create" class="btn btn-primary">Create zone from prefix</button>
		</div>
		<?php } ?>
	</div>
	<?php if($active_user->admin) { ?>
	<div role="tabpanel" class="tab-pane" id="create">
		<h2 class="sr-only">Create zone</h2>
		<form method="post" action="/zones" class="form-horizontal zoneadd">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<div class="form-group">
				<label for="name" class="col-sm-2 control-label">Zone name</label>
				<div class="col-sm-10">
					<input type="text" class="form-control" id="name" name="name" required pattern="\S+" maxlength="255">
				</div>
			</div>
			<div class="form-group">
				<label for="classification" class="col-sm-2 control-label">Zone classification</label>
				<div class="col-sm-10">
					<input type="text" class="form-control" id="classification" name="classification" required list="account_list" maxlength="40">
					<datalist id="account_list">
						<?php foreach($accounts as $account) { ?>
						<option value="<?php out($account)?>"><?php out($account)?></option>
						<?php } ?>
					</datalist>
				</div>
			</div>
			<fieldset>
				<legend>SOA</legend>
				<div class="form-group">
					<label class="col-sm-2 control-label">SOA templates</label>
					<div class="col-sm-10">
						<?php foreach($soa_templates as $template) { ?>
						<button type="button" class="btn btn-default soa-template" data-primary_ns="<?php out($template->primary_ns)?>" data-contact="<?php out($template->contact)?>" data-refresh="<?php out(DNSTime::abbreviate($template->refresh))?>" data-retry="<?php out(DNSTime::abbreviate($template->retry))?>" data-expire="<?php out(DNSTime::abbreviate($template->expire))?>" data-default_ttl="<?php out(DNSTime::abbreviate($template->default_ttl))?>" data-soa_ttl="<?php out(DNSTime::abbreviate($template->soa_ttl))?>" data-default="<?php out($template->default)?>"><?php out($template->name)?></button>
						<?php } ?>
						<a href="/templates/soa" class="btn btn-link">Edit templates</a>
					</div>
				</div>
				<div class="form-group">
					<label for="primary_ns" class="col-sm-2 control-label">Primary nameserver</label>
					<div class="col-sm-10">
						<input type="text" class="form-control" id="primary_ns" name="primary_ns" value="" required pattern="\S+">
					</div>
				</div>
				<div class="form-group">
					<label for="contact" class="col-sm-2 control-label">Contact</label>
					<div class="col-sm-10">
						<input type="text" class="form-control" id="contact" name="contact" value="" required pattern="\S+">
					</div>
				</div>
				<div class="form-group">
					<label for="refresh" class="col-sm-2 control-label">Refresh</label>
					<div class="col-sm-10">
						<input type="text" class="form-control" id="refresh" name="refresh" value="" required pattern="([0-9]+[smhdwSMHDW]?)+">
					</div>
				</div>
				<div class="form-group">
					<label for="retry" class="col-sm-2 control-label">Retry</label>
					<div class="col-sm-10">
						<input type="text" class="form-control" id="retry" name="retry" value="" required pattern="([0-9]+[smhdwSMHDW]?)+">
					</div>
				</div>
				<div class="form-group">
					<label for="expire" class="col-sm-2 control-label">Expire</label>
					<div class="col-sm-10">
						<input type="text" class="form-control" id="expire" name="expire" value="" required pattern="([0-9]+[smhdwSMHDW]?)+">
					</div>
				</div>
				<div class="form-group">
					<label for="default_ttl" class="col-sm-2 control-label">Default TTL</label>
					<div class="col-sm-10">
						<input type="text" class="form-control" id="default_ttl" name="default_ttl" value="" required pattern="([0-9]+[smhdwSMHDW]?)+">
					</div>
				</div>
				<div class="form-group">
					<label for="soa_ttl" class="col-sm-2 control-label">SOA TTL</label>
					<div class="col-sm-10">
						<input type="text" class="form-control" id="soa_ttl" name="soa_ttl" value="" required pattern="([0-9]+[smhdwSMHDW]?)+">
					</div>
				</div>
			</fieldset>
			<fieldset>
				<legend>Nameservers</legend>
				<div class="form-group">
					<label class="col-sm-2 control-label">Nameserver templates</label>
					<div class="col-sm-10">
						<?php foreach($ns_templates as $template) { ?>
						<button type="button" class="btn btn-default ns-template" data-nameservers="<?php out($template->nameservers)?>" data-default="<?php out($template->default)?>"><?php out($template->name)?></button>
						<?php } ?>
						<a href="/templates/ns" class="btn btn-link">Edit templates</a>
					</div>
				</div>
				<div class="form-group">
					<label for="nameservers" class="col-sm-2 control-label">Nameservers</label>
					<div class="col-sm-10">
						<textarea class="form-control" id="nameservers" name="nameservers" rows="3"></textarea>
					</div>
				</div>
			</fieldset>
			<div class="form-group">
				<div class="col-sm-offset-2 col-sm-10">
					<button type="submit" class="btn btn-primary" name="add_zone" value="1">Create zone</button>
				</div>
			</div>
		</form>
	</div>
	<?php } ?>
</div>
