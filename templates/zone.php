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
$active_user = $this->get('active_user');
$zone = $this->get('zone');
$rrsets = $this->get('rrsets');
$pending = $this->get('pending');
$changesets = $this->get('changesets');
$changeset_filters = $this->get('changeset_filters');
$changeset_pagecount = $this->get('changeset_pagecount');
$access = $this->get('access');
$accounts = $this->get('accounts');
$cryptokeys = $this->get('cryptokeys');
$allusers = $this->get('allusers');
$replication_types = $this->get('replication_types');
$local_zone = $this->get('local_zone');
$local_ipv4_ranges = $this->get('local_ipv4_ranges');
$local_ipv6_ranges = $this->get('local_ipv6_ranges');
$soa_templates = $this->get('soa_templates');
$dnssec_enabled = $this->get('dnssec_enabled');
$dnssec_edit = $this->get('dnssec_edit');
$deletion = $this->get('deletion');
$force_change_review = $this->get('force_change_review');
$force_change_comment = $this->get('force_change_comment');
$account_whitelist = $this->get('account_whitelist');
$force_account_whitelist = $this->get('force_account_whitelist');
$maxperpage = 1000;
$reverse = false;
global $output_formatter;
?>
<h1>
	<?php out(DNSZoneName::unqualify(punycode_to_utf8($zone->name)))?> zone
	<?php if(substr($zone->name, -14) == '.in-addr.arpa.') { $reverse = true; ?>
	<small>IPv4 reverse zone for <?php out(ipv4_reverse_zone_to_range($zone->name))?></small>
	<?php } elseif(substr($zone->name, -10) == '.ip6.arpa.') { $reverse = true; ?>
	<small>IPv6 reverse zone for <tt><?php out(ipv6_reverse_zone_to_range($zone->name))?></tt></small>
	<?php } ?>
</h1>
<ul class="nav nav-tabs" role="tablist">
	<li role="presentation" class="active"><a href="#records" aria-controls="records" role="tab" data-toggle="tab">Resource records</a></li>
	<li role="presentation"><a href="#pending" aria-controls="pending" role="tab" data-toggle="tab">Pending updates<?php if(count($pending) > 0) {?> <span class="badge"><?php out(count($pending))?></span><?php } ?></a></li>
	<li role="presentation"><a href="#soa" aria-controls="soa" role="tab" data-toggle="tab">Zone configuration</a></li>
	<?php if($dnssec_enabled) { ?>
	<li role="presentation"><a href="#dnssec" aria-controls="dnssec" role="tab" data-toggle="tab">DNSSEC</a></li>
	<?php } ?>
	<li role="presentation"><a href="#import" aria-controls="import" role="tab" data-toggle="tab">Export / Import</a></li>
	<?php if($active_user->admin) { ?>
	<li role="presentation"><a href="#tools" aria-controls="tools" role="tab" data-toggle="tab">Tools<?php if(!is_null($deletion)) { ?> <span class="badge">!</span><?php } ?></a></li>
	<?php } ?>
	<li role="presentation"><a href="#changelog" aria-controls="changelog" role="tab" data-toggle="tab">Changelog</a></li>
	<li role="presentation"><a href="#access" aria-controls="access" role="tab" data-toggle="tab">User access</a></li>
</ul>
<div class="tab-content">
	<div role="tabpanel" class="tab-pane active" id="records">
		<h2 class="sr-only">Resource records</h2>
		<form method="post" action="<?php outurl('/zones/'.urlencode(DNSZoneName::unqualify($zone->name)))?>" class="zoneedit" data-zone="<?php out($zone->name)?>" data-local-zone="<?php out($local_zone ? 1 : 0)?>" data-local-ipv4-ranges="<?php out($local_ipv4_ranges)?>" data-local-ipv6-ranges="<?php out($local_ipv6_ranges)?>">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<nav></nav>
			<table class="table table-bordered table-condensed table-hover stickyHeader rrsets">
				<thead>
					<tr>
						<th class="name">Name</th>
						<th class="type">Type</th>
						<th class="ttl">TTL</th>
						<th class="content">Content</th>
						<th class="enabled">Enabled</th>
						<th class="actions">Actions</th>
						<th class="comment">Comments</th>
					</tr>
				</thead>
				<tbody>
					<?php
					$rrsetnum = 0;
					foreach($rrsets as $rrset) {
						if($rrset->type == 'SOA') continue;
						if($rrset->type == 'NS' && !$active_user->admin) continue;
						$rrsetnum++;
						$rrs = $rrset->list_resource_records();
						$name = DNSName::abbreviate($rrset->name, $zone->name);
						$rowclasses = array('primary');
						$firstrow = reset($rrs);
						$alldisabled = true;
						foreach($rrs as $rr) {
							if(!$rr->disabled) $alldisabled = false;
						}
						if($firstrow->disabled) $rowclasses[] = 'disabled';
						if($alldisabled) $rowclasses[] = 'rrset-disable';
						if($rrsetnum > $maxperpage) $rowclasses[] = 'hidden';
						?>
					<tr data-name="<?php out(punycode_to_utf8($name))?>" data-type="<?php out($rrset->type)?>" data-rrsetnum="<?php out($rrsetnum)?>" class="<?php out(implode(' ', $rowclasses))?>">
						<td class="name" rowspan="<?php out(count($rrs))?>"><?php out(punycode_to_utf8($name))?></td>
						<td class="type" rowspan="<?php out(count($rrs))?>"><?php out($rrset->type)?></td>
						<td class="ttl" rowspan="<?php out(count($rrs))?>"><?php out(DNSTime::abbreviate($rrset->ttl))?></td>
						<?php
						$count = 0;
						foreach($rrs as $rr) {
							$rowclasses = array();
							if($rr->disabled) $rowclasses[] = 'disabled';
							if($rrsetnum > $maxperpage) $rowclasses[] = 'hidden';
							$count++;
							if($count > 1) {
								out('</tr><tr data-name="'.hesc($name).'" data-type="'.hesc($rrset->type).'" data-rrsetnum="'.hesc($rrsetnum).'"', ESC_NONE);
								if(count($rowclasses) > 0) {
									out(' class="'.hesc(implode(' ', $rowclasses)).'"', ESC_NONE);
								}
								out('>', ESC_NONE);
							}
							$rr->content = DNSContent::decode($rr->content, $rrset->type, $zone->name);
							?>
						<td class="content"><?php
							out($rr->content);
							switch($rrset->type) {
							case 'CNAME':
							case 'DNAME':
							case 'NS':
							case 'PTR':
							case 'MX':
							case 'SRV':
								if(substr($rr->content, -1) == '@') {
									?><span class="zone-hint"><?php out("{$zone->name}");?></span><?php
								} elseif(substr($rr->content, -1) != '.') {
									?><span class="zone-hint"><?php out(".{$zone->name}");?></span><?php
								}
								break;
							}
						?></td>
						<td class="enabled"><?php out($rr->disabled ? 'No' : 'Yes')?></td>
						<td class="actions">
							<button type="button" class="btn btn-default btn-xs delete-rr"><span class="glyphicon glyphicon-trash"></span> Delete</button>
						</td>
						<?php if($count == 1) { ?>
						<td class="comment" rowspan="<?php out(count($rrs))?>"><?php out($rrset->merge_comment_text())?></td>
						<?php } ?>
							<?php
						}
						?>
					</tr>
					<?php } ?>
				</tbody>
				<tfoot>
					<tr id="new_row">
						<td class="name"><input type="text" id="new_name" required pattern="(\S+)"></td>
						<td class="type">
							<select id="new_type" required>
								<option value=""></option>
								<!--
								Note: Regexp validating IP addresses is not the best way, but regexp is all that the pattern="" attribute will give us.
								The following regexps are not perfect, they will allow a select few invalid cases, and the IPv6 one is very large, but
								they are better than no client-side validation at all.
								-->
								<?php if($reverse) { ?>
								<option value="CNAME" data-content-pattern="\S+">CNAME</option>
								<option value="DS" data-content-pattern="[0-9]+\s+[0-9]+\s+[0-9]+\s+[a-zA-Z0-9]+">DS</option>
								<?php if($active_user->admin) { ?>
								<option value="NS" data-content-pattern="\S*">NS</option>
								<?php } ?>
								<option value="PTR" data-content-pattern="\S+">PTR</option>
								<?php } else { ?>
								<option value="A" data-content-pattern="((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])">A</option>
								<option value="AAAA" data-content-pattern="(([0-9a-fA-F]{1,4}:){7,7}[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,7}:|([0-9a-fA-F]{1,4}:){1,6}:[0-9a-fA-F]{1,4}|([0-9a-fA-F]{1,4}:){1,5}(:[0-9a-fA-F]{1,4}){1,2}|([0-9a-fA-F]{1,4}:){1,4}(:[0-9a-fA-F]{1,4}){1,3}|([0-9a-fA-F]{1,4}:){1,3}(:[0-9a-fA-F]{1,4}){1,4}|([0-9a-fA-F]{1,4}:){1,2}(:[0-9a-fA-F]{1,4}){1,5}|[0-9a-fA-F]{1,4}:((:[0-9a-fA-F]{1,4}){1,6})|:((:[0-9a-fA-F]{1,4}){1,7}|:)|fe80:(:[0-9a-fA-F]{0,4}){0,4}%[0-9a-zA-Z]{1,}|::(ffff(:0{1,4}){0,1}:){0,1}((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])|([0-9a-fA-F]{1,4}:){1,4}:((25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9])\.){3,3}(25[0-5]|(2[0-4]|1{0,1}[0-9]){0,1}[0-9]))">AAAA</option>
								<option value="ALIAS" data-content-pattern="\S+">ALIAS</option>
								<option value="CAA" data-content-pattern="[0-9]+\s+\S+\s+\S+">CAA</option>
								<option value="CNAME" data-content-pattern="\S+">CNAME</option>
								<!-- DHCID regex contributed under CC BY-SA 4.0 by njzk2 (https://stackoverflow.com/users/671543/njzk2) on Stack Overflow: https://stackoverflow.com/a/5885097 -->
								<option value="DHCID" data-content-pattern="^(?:[A-Za-z0-9+\/]{4})*(?:[A-Za-z0-9+\/]{2}==|[A-Za-z0-9+\/]{3}=|[A-Za-z0-9+\/]{4})$">DHCID</option>
								<option value="DNAME" data-content-pattern="\S+">DNAME</option>
								<option value="DNSKEY" data-content-pattern="[0-9]+\s+[0-9]+\s+[0-9]+\s+.+">DNSKEY</option>
								<option value="DS" data-content-pattern="[0-9]+\s+[0-9]+\s+[0-9]+\s+[a-zA-Z0-9]+">DS</option>
								<option value="NAPTR" data-content-pattern="[0-9]+\s+[0-9]+\s+&quot;[a-zA-Z0-9]+&quot;\s*&quot;[^&quot;]*&quot;\s+&quot;[^&quot;]*&quot;\s+\S+">NAPTR</option>
								<?php if($active_user->admin) { ?>
								<option value="NS" data-content-pattern="\S*">NS</option>
								<?php } ?>
								<option value="LOC" data-content-pattern="[0-9]{1,2} ([0-9]{1,3} ([0-9]{1,2}(\.[0-9]{1,3})?)?)? [NS] [0-9]{1,2} ([0-9]{1,3} ([0-9]{1,2}(\.[0-9]{1,3})?)?)? [EW] -?[0-9]+(\.[0-9]{1,2})?m?( [0-9]+(\.[0-9]{1,2})?m?( [0-9]+(\.[0-9]{1,2})?m?( [0-9]+(\.[0-9]{1,2})?m?)))">LOC</option>
								<option value="LUA" data-content-pattern=".*">LUA</option>
								<option value="MX" data-content-pattern="[0-9]+\s+\S+">MX</option>
								<option value="PTR" data-content-pattern="\S+">PTR</option>
								<option value="SRV" data-content-pattern="[0-9]+\s+[0-9]+\s+[0-9]+\s+\S+">SRV</option>
								<option value="SSHFP" data-content-pattern="[0-4]\s+[0-2]\s+[0-9a-f]+">SSHFP</option>
								<option value="TLSA" data-content-pattern="[0-9]+\s+[0-9]+\s+[0-9]+\s+[a-zA-Z0-9]+">TLSA</option>
								<option value="TXT" data-content-pattern=".*">TXT</option>
								<?php } ?>
							</select>
						</td>
						<td class="ttl"><input type="text" id="new_ttl" required pattern="([0-9]+[smhdwSMHDW]?)+" value="<?php out(DNSTime::abbreviate($zone->soa->default_ttl))?>"></td>
						<td class="content"><input type="text" id="new_content" required></td>
						<td class="enabled">
							<select id="new_enabled" required>
								<option value="Yes">Yes</option>
								<option value="No">No</option>
							</select>
						</td>
						<td class="actions"><button type="button" id="new_add" disabled class="btn btn-default btn-xs"><span class="glyphicon glyphicon-plus"></span> Add</button></td>
						<td class="comment"><input type="text" id="new_comment"></td>
					</tr>
				</tfoot>
			</table>
			<nav></nav>
			<input type="hidden" id="maxperpage" value="<?php out($maxperpage)?>">
		</form>
		<form method="post" action="<?php outurl('/zones/'.urlencode(DNSZoneName::unqualify($zone->name)))?>">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<div id="updates" class="hide">
				<h3>Updates</h3>
				<ul id="updates_list">
				</ul>
				<ul id="collisions_list">
				</ul>
				<input type="hidden" name="serial" value="<?php out($zone->soa->serial)?>">
				<div class="form-group"><label for="comment">Update comment</label><input type="text" id="comment" name="comment" class="form-control"<?php if($force_change_comment) out(' required');?>></div>
				<div id="errors"></div>
				<?php if(($active_user->admin || $active_user->access_to($zone) == 'administrator') && !$force_change_review) { ?>
				<p><button type="submit" id="zonesubmit" name="update_rrs" value="save" class="btn btn-primary">Save changes</button></p>
				<?php } else { ?>
				<p><button type="submit" id="zonesubmit" name="update_rrs" value="request" class="btn btn-primary">Request changes</button></p>
				<?php } ?>
			</div>
		</form>
	</div>
	<div role="tabpanel" class="tab-pane" id="pending">
		<h2 class="sr-only">Pending updates</h2>
		<?php if(count($pending) == 0) { ?>
		<p>There are no pending updates.</p>
		<?php } else { ?>
		<?php foreach($pending as $update) { ?>
		<?php
		$data = json_decode($update->raw_data);
		$invalid_count = 0;
		foreach($data->actions as $action) {
			if($action->action == 'add') {
				$fullname = utf8_to_punycode(DNSName::canonify($action->name, $zone->name));
				if(isset($rrsets[$fullname.' '.$action->type])) {
					$invalid_count++;
					$action->problem = "The record \"{$action->name} {$action->type}\" already exists in this zone.";
				}
			} elseif($action->action == 'update') {
				$fullname = utf8_to_punycode(DNSName::canonify($action->oldname, $zone->name));
				if(!isset($rrsets[$fullname.' '.$action->oldtype])) {
					$invalid_count++;
					$action->problem = "The record \"{$action->name} {$action->type}\" no longer exists in this zone.";
				}
			}
		}
		?>
		<form method="post" action="<?php outurl('/zones/'.urlencode(DNSZoneName::unqualify($zone->name)))?>" class="pending_update">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<div class="panel panel-default">
				<div class="panel-heading">
					<div class="pull-right">
						<?php if($active_user->admin || $active_user->access_to($zone) == 'administrator') { ?>
						<?php if($invalid_count == 0) { ?>
						<button type="submit" name="approve_update" value="<?php out($update->id)?>" class="btn btn-xs btn-success"><span class="glyphicon glyphicon-ok"></span> Approve</button>
						<?php } else { ?>
						<span class="text-danger">Update conflicts with more recent changes in this zone</span>
						<?php } ?>
						<input type="hidden" name="reject_reason">
						<button type="submit" name="reject_update" value="<?php out($update->id)?>" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-remove"></span> Reject</button>
						<?php } elseif($update->author->id == $active_user->id) { ?>
						<button type="submit" name="cancel_update" value="<?php out($update->id)?>" class="btn btn-xs btn-danger"><span class="glyphicon glyphicon-trash"></span> Cancel request</button>
						<?php } ?>
					</div>
					<h3 class="panel-title">Change #<?php out($update->id)?> requested by <?php out($update->author->name)?> on <?php out($update->request_date->format('Y-m-d H:i:s'))?></h3>
				</div>
				<div class="panel-body">
					<?php
					foreach($data->actions as $action) {
						$current = array();
						$current_ttl = '';
						$current_comment = '';
						if($action->action == 'update') {
							$fullname = utf8_to_punycode(DNSName::canonify($action->oldname, $zone->name));
							if(isset($rrsets[$fullname.' '.$action->oldtype])) {
								$current_rrset = $rrsets[$fullname.' '.$action->oldtype];
								$current = $current_rrset->list_resource_records();
								$current_ttl = DNSTime::abbreviate($current_rrset->ttl);
								$current_comment = $current_rrset->merge_comment_text();
							}
						}
						?>
						<?php if(isset($action->problem)) { ?>
						<p class="alert alert-danger"><?php out($action->problem)?></p>
						<?php } ?>
						<?php if($action->action == 'add') { ?>
						<h4>Add RRSet: <tt><?php out($action->name.' '.$action->type)?></tt>, TTL: <?php out($action->ttl)?></h4>
						<?php } else { ?>
						<h4>Update RRSet: <tt><?php show_diff($action->oldname.' '.$action->oldtype, $action->name.' '.$action->type)?></tt>, TTL: <?php show_diff($current_ttl, $action->ttl)?></h4>
						<?php } ?>
						<table class="table table-condensed">
							<thead>
								<tr>
									<th>Content</th>
									<th>Enabled</th>
								</tr>
							</thead>
							<tbody>
								<?php
								foreach($current as $record) {
									$rr_match = false;
									foreach($action->records as $ref => $new_record) {
										if($new_record->content == $record->content) {
											$rr_match = true;
											unset($action->records[$ref]);
											break;
										}
									}
									if($rr_match && !isset($new_record->delete)) {
										?>
										<tr>
											<td><?php out($record->content)?></td>
											<td><?php show_diff($record->disabled ? 'No' : 'Yes', $new_record->enabled)?></td>
										</tr>
										<?php
									} else {
										?>
										<tr>
											<td><del><?php out($record->content)?></del></td>
											<td><del><?php out($record->disabled ? 'No' : 'Yes')?></del></td>
										</tr>
										<?php
									}
								}
								?>
								<?php foreach($action->records ?? [] as $record) { ?>
								<?php if(isset($record->delete)) { ?>
								<tr>
									<td><del><?php out($record->content)?></del></td>
									<td><del><?php out($record->enabled)?></del></td>
								</tr>
								<?php } else { ?>
								<tr>
									<td><ins><?php out($record->content)?></ins></td>
									<td><ins><?php out($record->enabled)?></ins></td>
								</tr>
								<?php } ?>
								<?php } ?>
							</tbody>
						</table>
						<p>RRSet comment: <?php show_diff($current_comment, $action->comment ?? null )?></p>
						<?php
					}
					?>
					</ul>
					<p>Change comment: <q><?php out(isset($data->comment) ? $data->comment : '')?></q></p>
				</div>
			</div>
		</form>
		<?php } ?>
		<?php } ?>
	</div>
	<div role="tabpanel" class="tab-pane" id="soa">
		<h2 class="sr-only">Zone configuration</h2>
		<form method="post" action="<?php outurl('/zones/'.urlencode(DNSZoneName::unqualify($zone->name)))?>" class="form-horizontal zoneeditsoa">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<h3>Zone settings</h3>
			<div class="form-group">
				<label for="kind" class="col-sm-2 control-label">Replication type</label>
				<div class="col-sm-10">
					<?php if($active_user->admin) { ?>
					<select id="kind" name="kind" class="form-control" required>
						<?php foreach($replication_types as $type) { ?>
						<option value="<?php out($type->name)?>"<?php if($zone->kind == $type->name) out(' selected')?>><?php out($type->name)?></option>
						<?php } ?>
					</select>
					<?php } else { ?>
					<p class="form-control-static"><?php out($zone->kind)?></p>
					<?php } ?>
				</div>
			</div>
			<div class="form-group">
				<label for="classification" class="col-sm-2 control-label">Classification</label>
				<div class="col-sm-10">
					<?php if($active_user->admin) { ?>
					<?php if($force_account_whitelist) { ?>
					<select class="form-control" id="classification" name="classification">
						<?php foreach($account_whitelist as $account) { ?>
						<option value="<?php out($account)?>"<?php if($account == $zone->account) out(' selected', ESC_NONE)?>><?php out($account)?></option>
						<?php } ?>
						<?php if(!in_array($zone->account, $account_whitelist)) { ?>
						<option value="<?php out($zone->account)?>" selected><?php out($zone->account)?></option>
						<?php } ?>
					</select>
					<?php } else { ?>
					<input type="text" class="form-control" id="classification" name="classification" list="account_list" required maxlength="40" value="<?php out($zone->account)?>">
					<datalist id="account_list">
						<?php foreach($accounts as $account) { ?>
						<option value="<?php out($account)?>"><?php out($account)?></option>
						<?php } ?>
					</datalist>
					<?PHP } ?>
					<?php } else { ?>
					<p class="form-control-static"><?php out($zone->account)?></p>
					<?php } ?>
				</div>
			</div>
			<h3>Start of authority (SOA)</h3>
			<?php if($active_user->admin) { ?>
			<div class="form-group">
				<label class="col-sm-2 control-label">SOA templates</label>
				<div class="col-sm-10">
					<?php foreach($soa_templates as $template) { ?>
					<button type="button" class="btn btn-default soa-template" data-primary_ns="<?php out($template->primary_ns)?>" data-contact="<?php out($template->contact)?>" data-refresh="<?php out(DNSTime::abbreviate($template->refresh))?>" data-retry="<?php out(DNSTime::abbreviate($template->retry))?>" data-expire="<?php out(DNSTime::abbreviate($template->expire))?>" data-default_ttl="<?php out(DNSTime::abbreviate($template->default_ttl))?>" data-soa_ttl="<?php out(DNSTime::abbreviate($template->soa_ttl))?>"><?php out($template->name)?></button>
					<?php } ?>
					<a href="<?php outurl('/templates/soa')?>" class="btn btn-link">Edit templates</a>
				</div>
			</div>
			<?php } ?>
			<div class="form-group">
				<label for="primary_ns" class="col-sm-2 control-label">Primary nameserver</label>
				<div class="col-sm-10">
					<?php if($active_user->admin) { ?>
					<input type="text" class="form-control" id="primary_ns" name="primary_ns" required pattern="\S+" value="<?php out($zone->soa->primary_ns)?>">
					<?php } else { ?>
					<p class="form-control-static"><?php out($zone->soa->primary_ns)?></p>
					<?php } ?>
				</div>
			</div>
			<div class="form-group">
				<label for="contact" class="col-sm-2 control-label">Contact</label>
				<div class="col-sm-10">
					<?php if($active_user->admin) { ?>
					<input type="text" class="form-control" id="contact" name="contact" required pattern="\S+" value="<?php out($zone->soa->contact)?>">
					<?php } else { ?>
					<p class="form-control-static"><?php out($zone->soa->contact)?></p>
					<?php } ?>
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label">Serial number</label>
				<div class="col-sm-10">
					<p class="form-control-static"><?php out($zone->soa->serial)?></p>
				</div>
			</div>
			<div class="form-group">
				<label for="refresh" class="col-sm-2 control-label"><abbr title="Indicates the time when the slave will try to refresh the zone from the master">Refresh</abbr></label>
				<div class="col-sm-10">
					<?php if($active_user->admin) { ?>
					<input type="text" class="form-control" id="refresh" name="refresh" required pattern="([0-9]+[smhdwSMHDW]?)+" maxlength="40" value="<?php out(DNSTime::abbreviate($zone->soa->refresh))?>">
					<?php } else { ?>
					<p class="form-control-static"><?php out(DNSTime::abbreviate($zone->soa->refresh))?></p>
					<?php } ?>
				</div>
			</div>
			<div class="form-group">
				<label for="retry" class="col-sm-2 control-label"><abbr title="Defines the time between retries if the slave (secondary) fails to contact the master when refresh (above) has expired. Typical values would be 180 (3 minutes) to 900 (15 minutes) or higher.">Retry</abbr></label>
				<div class="col-sm-10">
					<?php if($active_user->admin) { ?>
					<input type="text" class="form-control" id="retry" name="retry" required pattern="([0-9]+[smhdwSMHDW]?)+" maxlength="40" value="<?php out(DNSTime::abbreviate($zone->soa->retry))?>">
					<?php } else { ?>
					<p class="form-control-static"><?php out(DNSTime::abbreviate($zone->soa->retry))?></p>
					<?php } ?>
				</div>
			</div>
			<div class="form-group">
				<label for="expire" class="col-sm-2 control-label"><abbr title="Indicates when the zone data is no longer authoritative. Used by Slave (Secondary) servers only.">Expire</abbr></label>
				<div class="col-sm-10">
					<?php if($active_user->admin) { ?>
					<input type="text" class="form-control" id="expire" name="expire" required pattern="([0-9]+[smhdwSMHDW]?)+" maxlength="40" value="<?php out(DNSTime::abbreviate($zone->soa->expiry))?>">
					<?php } else { ?>
					<p class="form-control-static"><?php out(DNSTime::abbreviate($zone->soa->expiry))?></p>
					<?php } ?>
				</div>
			</div>
			<div class="form-group">
				<label for="default_ttl" class="col-sm-2 control-label"><abbr title="The time a NAME ERROR = NXDOMAIN result may be cached by any resolver.">Default TTL</abbr></label>
				<div class="col-sm-10">
					<?php if($active_user->admin) { ?>
					<input type="text" class="form-control" id="default_ttl" name="default_ttl" required pattern="([0-9]+[smhdwSMHDW]?)+" maxlength="40" value="<?php out(DNSTime::abbreviate($zone->soa->default_ttl))?>">
					<?php } else { ?>
					<p class="form-control-static"><?php out(DNSTime::abbreviate($zone->soa->default_ttl))?></p>
					<?php } ?>
				</div>
			</div>
			<div class="form-group">
				<label for="soa_ttl" class="col-sm-2 control-label"><abbr title="The time this SOA record may be cached by any resolver.">SOA TTL</abbr></label>
				<div class="col-sm-10">
					<?php if($active_user->admin) { ?>
					<input type="text" class="form-control" id="soa_ttl" name="soa_ttl" required pattern="([0-9]+[smhdwSMHDW]?)+" maxlength="40" value="<?php out(DNSTime::abbreviate($zone->soa->ttl))?>">
					<?php } else { ?>
					<p class="form-control-static"><?php out(DNSTime::abbreviate($zone->soa->ttl))?></p>
					<?php } ?>
				</div>
			</div>
			<hr>
			<?php if($active_user->admin) { ?>
			<div class="form-group">
				<label for="soa_change_comment" class="col-sm-2 control-label">Change comment</label>
				<div class="col-sm-10">
					<input type="text" class="form-control" id="soa_change_comment" name="soa_change_comment" value=""<?php if($force_change_comment) out(' required');?>>
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-offset-2 col-sm-10">
					<button type="submit" name="update_zone" value="1" class="btn btn-primary">Save changes</button>
				</div>
			</div>
			<?php } ?>
		</form>
	</div>
	<?php if($dnssec_enabled) { ?>
	<div role="tabpanel" class="tab-pane" id="dnssec">
		<h2 class="sr-only">DNSSEC</h2>
		<?php if($zone->dnssec) { ?>
		<?php if(!$zone->api_rectify && $active_user->admin && $dnssec_edit) { ?>
		<form method="post" action="<?php outurl('/zones/'.urlencode(DNSZoneName::unqualify($zone->name)))?>">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<div class="alert alert-warning">
				<p><strong>Warning:</strong> API-rectify is not enabled for this zone. It is recommended to enable it so that manual DNSSEC rectification is not needed.</p>
				<p><button type="submit" class="btn btn-success" name="enable_api_rectify" value="1">Enable API-rectify</button></p>
			</div>
		</form>
		<?php } ?>
		<h3>Crypto keys</h3>
		<?php foreach($cryptokeys as $cryptokey) { ?>
		<?php
		list($dnskey_flags, $dnskey_protocol, $dnskey_algorithm, $dnskey_keydata) = preg_split('/\s+/', $cryptokey->dnskey);
		?>
		<div class="panel <?php out($cryptokey->active ? 'panel-success' : 'panel-default') ?>">
			<div class="panel-heading">
				<h3 class="panel-title"><?php out($cryptokey->type.' #'.$cryptokey->id.': '.strtoupper($cryptokey->keytype))?></h3>
			</div>
			<div class="panel-body">
				<dl>
					<dt>Info</dt>
					<dd><code>ID = <?php out($cryptokey->id)?> (<?php out(strtoupper($cryptokey->keytype))?>), flags = <?php out($cryptokey->flags)?>, tag = <?php out(DNSKEY::get_tag($dnskey_flags, $dnskey_protocol, $dnskey_algorithm, $dnskey_keydata))?>, algo = <?php out($dnskey_algorithm)?>, bits = <?php out($cryptokey->bits)?>  <?php out($cryptokey->active ? 'Active' : 'Inactive')?> ( <?php out($cryptokey->algorithm)?> )</code></dd>
					<dt>DNSKEY</dt>
					<dd><code><?php out($zone->name.' IN DNSKEY '.$cryptokey->dnskey.' ; ( '.$cryptokey->algorithm.' )')?></code></dd>
					<?php if(isset($cryptokey->ds)) { ?>
					<dt>DS records</dt>
					<dd>
						<ul>
							<?php foreach($cryptokey->ds as $ds) { ?>
							<li><code><?php out($zone->name.' IN DS '.$ds.' ; ( '.DS::get_digest_type($ds).' )') ?></code></li>
							<?php } ?>
						</ul>
					</dd>
					<?php } ?>
				</dl>
			</div>
		</div>
		<?php } ?>
		<?php } else { ?>
		<p>DNSSEC is not currently enabled for this zone.</p>
		<?php } ?>
		<?php if($active_user->admin && $dnssec_edit) { ?>
		<?php if($zone->dnssec) { ?>
		<h3>Disable DNSSEC</h3>
		<form method="post" action="<?php outurl('/zones/'.urlencode(DNSZoneName::unqualify($zone->name)))?>" class="disablednssec">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<p>
				<p class="alert alert-danger">
					<strong>Warning!</strong> Disabling DNSSEC for the zone will permanently delete the zone's DNSSEC private keys in PowerDNS.
				</p>
				<div class="checkbox"><label><input type="checkbox" name="disable_dnssec" value="1"> Confirm DNSSEC disable</label></div>
				<button type="submit" class="btn btn-danger">Disable DNSSEC for <?php out(DNSZoneName::unqualify($zone->name))?><span>…</span></button>
			</p>
		</form>
		<?php } else { ?>
		<h3>Enable DNSSEC</h3>
		<form method="post" action="<?php outurl('/zones/'.urlencode(DNSZoneName::unqualify($zone->name)))?>">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<p>
				<button type="submit" name="enable_dnssec" value="1" class="btn btn-primary">Enable DNSSEC for <?php out(DNSZoneName::unqualify($zone->name))?></button>
			</p>
		</form>
		<?php } ?>
		<?php } ?>
	</div>
	<?php } ?>
	<div role="tabpanel" class="tab-pane" id="import">
		<h2 class="sr-only">Export / Import</h2>
		<h3>Export zone</h3>
		<a href="<?php outurl('/zones/'.urlencode(DNSZoneName::unqualify($zone->name)))?>/export" class="btn btn-primary">Export zone in bind9 format</a>
		<h3>Import zone</h3>
		<form method="post" action="<?php outurl('/zones/'.urlencode(DNSZoneName::unqualify($zone->name)).'/import')?>" enctype="multipart/form-data">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<div class="form-group">
				<label>bind9 zone file</label>
				<input type="file" name="zonefile" class="form-control" required>
			</div>
			<div class="radio">
				<label>
					<input type="radio" name="comment_handling" value="parse" checked>
					Attempt to parse lines starting with <code>;</code> as disabled records
				</label>
			</div>
			<div class="radio">
				<label>
					<input type="radio" name="comment_handling" value="ignore">
					Completely ignore lines starting with <code>;</code>
				</label>
			</div>
			<div class="form-group">
				<button type="submit" class="btn btn-primary">Import zone file…</button>
			</div>
		</form>
	</div>
	<?php if($active_user->admin) { ?>
	<div role="tabpanel" class="tab-pane" id="tools">
		<h2 class="sr-only">Tools</h2>
		<h3>Split zone</h3>
		<p>This tool allows you to split records off into a separate new zone.</p>
		<form method="post" action="<?php outurl('/zones/'.urlencode(DNSZoneName::unqualify($zone->name)).'/split')?>" class="form-inline">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<label for="zone_split_prefix" class="sr-only">Suffix</label>
			<div class="input-group">
				<div class="input-group-addon">*.</div>
				<input type="text" id="zone_split_suffix" name="suffix" class="form-control" required>
				<div class="input-group-addon">.<?php out(punycode_to_utf8(DNSZoneName::unqualify($zone->name)))?></div>
			</div>
			<button type="submit" class="btn btn-primary">Split matching records into new zone…</button>
		</form>
		<h3>Delete zone</h3>
		<?php if(is_null($deletion)) { ?>
		<p>Permanently delete a zone from the DNS server and archive it. Zone deletion requires confirmation from another user.</p>
		<form method="post" action="<?php outurl('/zones/'.urlencode(DNSZoneName::unqualify($zone->name)))?>" class="zonedelete form-inline">
			<p>
				<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
				<div class="checkbox"><label><input type="checkbox" name="request_delete_zone" value="1"> Confirm zone deletion request</label></div>
				<button type="submit" class="btn btn-danger">Request zone deletion<span>…</span></button>
			</p>
		</form>
		<?php } elseif(is_null($deletion['confirm_date'])) { ?>
		<div class="panel panel-primary">
			<div class="panel-heading">
				<h4 class="panel-title">Deletion request pending</h4>
			</div>
			<div class="panel-body">
				<p><a href="<?php outurl('/users/'.urlencode($deletion['requester']->uid))?>"><?php out($deletion['requester']->name)?></a> has requested that this zone be deleted from the DNS server. This request must be confirmed by another administrator.</p>
				<form method="post" action="<?php outurl('/zones/'.urlencode(DNSZoneName::unqualify($zone->name)))?>" class="zonedelete form-inline">
					<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
					<button type="submit" class="btn btn-default" name="cancel_delete_zone" value="1">Abort request</button>
					<?php if($deletion['requester']->id != $active_user->id) { ?>
					<div class="checkbox"><label><input type="checkbox" name="confirm_delete_zone" value="1"> Confirm zone deletion</label></div>
					<button type="submit" class="btn btn-danger">Delete zone<span>…</span></button>
					<?php } ?>
				</form>
			</div>
		</div>
		<?php } else { ?>
		<div class="alert alert-warning">
			<p>This zone was previously deleted on <?php out($deletion['confirm_date']->format('Y-m-d H:i:s'))?> by <a href="<?php outurl('/users/'.urlencode($deletion['requester']->uid))?>"><?php out($deletion['requester']->name)?></a> and <a href="<?php outurl('/users/'.urlencode($deletion['confirmer']->uid))?>"><?php out($deletion['confirmer']->name)?></a>.</p>
			<p>This is a snapshot of the zone's contents prior to its deletion.</p>
			<pre class="source"><?php out($deletion['zone_export'])?></pre>
			<form method="post" action="<?php outurl('/zones/'.urlencode(DNSZoneName::unqualify($zone->name)))?>" class="zonedelete form-inline">
				<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
				<button type="submit" class="btn btn-default" name="remove_delete_record" value="1">Remove snapshot</button>
			</form>
		</div>
		<?php } ?>
		<?php if($zone->kind == 'Master') { ?>
		<div class="alert alert-warning">
			<strong>Warning:</strong> Deleting a zone that uses master/slave replication will not delete the zone from the slave server(s).
		</div>
		<?php } ?>
	</div>
	<?php } ?>
	<div role="tabpanel" class="tab-pane" id="changelog">
		<?php
		$changeset_page = $changeset_filters['page'];
		$start_date = isset($changeset_filters['start_date']) ? $changeset_filters['start_date']->format("Y-m-d") : "";
		$end_date = isset($changeset_filters['end_date']) ? $changeset_filters['end_date']->format("Y-m-d") : "";
		$comment_query = isset($changeset_filters['comment']) ? $changeset_filters['comment'] : "";
		function pagination($page) {
			return out("?" . http_build_query(array_merge($_GET, ['page'=>$page])));
		}
		?>
		<h2 class="sr-only">Changelog</h2>
		<form class="form-inline" id="changelog-search" action="" method="GET">
			<input placeholder="search comments" name="changeset_comment" value="<?php out($comment_query)?>" class="form-control">
			<label>from: <input type="date" name="changeset_start" value="<?php out($start_date)?>" class="form-control"></label>
			<label>to: <input type="date" name="changeset_end" value="<?php out($end_date)?>" class="form-control"></label>
			<input type="submit" value="Search" class="btn btn-primary">
		</form>
		<button class="btn btn-default" id="changelog-expand-all">expand all</button>
		<button class="btn btn-default" id="changelog-collapse-all">collapse all</button>
		<?php if(count($changesets) == 0) { ?>
		<p>No changes have been made to this zone.</p>
		<?php } ?>
		<table class="table table-condensed table-hover changelog">
			<thead>
				<tr>
					<th>Date / time</th>
					<th>Comment</th>
					<th>Requester</th>
					<th>Author</th>
					<th>Changes</th>
					<th></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($changesets as $changeset) { ?>
				<tr data-zone="<?php out($zone->name)?>" data-changeset="<?php out($changeset->id)?>">
					<td class="nowrap"><?php out($changeset->change_date->format('Y-m-d H:i:s'))?></td>
					<td><?php out($output_formatter->changeset_comment_format($changeset->comment), ESC_NONE) ?></td>
					<td class="nowrap"><?php if($changeset->requester) { ?><a href="<?php outurl('/users/'.urlencode($changeset->requester->uid))?>"><?php out($changeset->requester->name)?><?php } ?></td>
					<td class="nowrap"><a href="<?php outurl('/users/'.urlencode($changeset->author->uid))?>"><?php out($changeset->author->name)?></td>
					<td><?php out('-'.$changeset->deleted.'/+'.$changeset->added)?></td>
					<td></td>
				</tr>
				<?php } ?>
			</tbody>
		</table>
		<ul class="pagination">
			<?php if ($changeset_page > 1) { ?>
				<li><a href="<?php pagination(1)?>">First</a></li>
				<li><a href="<?php pagination($changeset_page-1)?>">Previous</a></li>
			<?php } else { ?>
				<li class="disabled"><a>First</a></li>
				<li class="disabled"><a>Previous</a></li>
			<?php } ?>
			<!-- 5 pages before and after the current page (if available). '...' to suggest that there are more pages -->
			<?php if ($changeset_page-5 > 1) { ?>
				<li class="disabled"><a>&hellip;</a></li>
			<?php } ?>
			<?php for($i = max($changeset_page-5, 1); $i <= min($changeset_page+5, $changeset_pagecount); $i++) { ?>
				<li class="<?php if($i == $changeset_page) { out('active'); }?>"><a href="<?php pagination($i)?>"><?php out($i)?></a></li>
			<?php } ?>
			<?php if ($changeset_page+5 < $changeset_pagecount) { ?>
				<li class="disabled"><a>&hellip;</a></li>
			<?php } ?>
			<?php if ($changeset_page < $changeset_pagecount) { ?>
				<li><a href="<?php pagination($changeset_page+1)?>">Next</a></li>
				<li><a href="<?php pagination($changeset_pagecount)?>">Last</a></li>
			</li>
			<?php } else { ?>
				<li class="disabled"><a>Next</a></li>
				<li class="disabled"><a>Last</a></li>
			<?php } ?>
		</ul>
	</div>
	<div role="tabpanel" class="tab-pane" id="access">
		<h2 class="sr-only">User access</h2>
		<?php if(count($access) == 0) { ?>
		<p>No users have been assigned to this zone.</p>
		<?php } else { ?>
		<form method="post" action="<?php outurl('/zones/'.urlencode(DNSZoneName::unqualify($zone->name)))?>">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<table class="table table-condensed table-bordered">
				<thead>
					<tr>
						<th>Name</th>
						<th>Access level</th>
						<?php if($active_user->admin) { ?>
						<th>Actions</th>
						<?php } ?>
					</tr>
				</thead>
				<tbody>
					<?php foreach($access as $rule) { ?>
					<tr>
						<td><?php out($rule->user->name)?></td>
						<td><?php out(ucfirst($rule->level))?></td>
						<?php if($active_user->admin) { ?>
						<td><button type="submit" name="delete_access" value="<?php out($rule->user->uid)?>" class="btn btn-default btn-xs"><span class="glyphicon glyphicon-trash"></span> Remove</button></td>
						<?php } ?>
					</tr>
					<?php } ?>
				</tbody>
			</table>
		</form>
		<?php } ?>
		<?php if($active_user->admin) { ?>
		<form method="post" action="<?php outurl('/zones/'.urlencode(DNSZoneName::unqualify($zone->name)))?>" class="form-horizontal">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<div class="form-group">
				<label for="uid" class="col-sm-2 control-label">Username</label>
				<div class="col-sm-6">
					<input type="text" id="uid" name="uid" class="form-control" placeholder="Username" required list="userlist">
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label">Access level</label>
				<div class="col-sm-6">
					<div class="radio">
						<label>
							<input type="radio" name="level" value="administrator" checked>
							Administrator&mdash;can directly edit any records in the zone (except for SOA and NS)
						</label>
					</div>
					<div class="radio">
						<label>
							<input type="radio" name="level" value="operator">
							Operator&mdash;can request changes to any records in the zone (except for SOA and NS), and these changes will then have to be approved by an administrator
						</label>
					</div>
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-offset-2 col-sm-6">
					<button type="submit" name="add_access" value="1" class="btn btn-primary">Add user to zone</button>
				</div>
			</div>
			<datalist id="userlist">
				<?php foreach($allusers as $user) { ?>
				<option value="<?php out($user->uid)?>" label="<?php out($user->name)?>">
				<?php } ?>
			</datalist>
		</form>
		<?php } ?>
	</div>
</div>
