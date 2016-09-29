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
$zone = $this->get('zone');
$modifications = $this->get('modifications');
$checked = 'checked ';
$count = 0;
$limit = 2500;
?>
<h1>Import preview for <?php out(DNSZoneName::unqualify(idn_to_utf8($zone->name, 0, INTL_IDNA_VARIANT_UTS46)))?> zone update</h1>
<?php if(count($modifications['add']) == 0 && count($modifications['update']) == 0 && count($modifications['delete']) == 0) { ?>
<p>No changes have been made! <a href="/zones/<?php out(DNSZoneName::unqualify($zone->name), ESC_URL)?>">Go back</a>.</p>
<?php } else { ?>
<form method="post" action="/zones/<?php out(DNSZoneName::unqualify($zone->name), ESC_URL)?>" class="zoneedit">
	<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
	<?php if(count($modifications['add']) > 0) { ?>
	<h2>New resource recordsets</h2>
	<table class="table table-bordered changepreview">
		<thead>
			<tr>
				<th class="name">Name</th>
				<th class="type">Type</th>
				<th class="ttl">TTL</th>
				<th>Data</th>
				<th>Comments</th>
				<th class="confirm">Okay to add?</th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach($modifications['add'] as $mod) {
				$count++;
				if($count > $limit) $checked = '';
				?>
			<tr>
				<td><?php out(DNSName::abbreviate($mod['new']->name, $zone->name))?></td>
				<td><?php out($mod['new']->type)?></td>
				<td><?php out(DNSTime::abbreviate($mod['new']->ttl))?></td>
				<td>
					<ul class="plain">
						<?php foreach($mod['new']->list_resource_records() as $rr) { ?>
						<li><?php out('Content: '.$rr->content.', Enabled: '.($rr->disabled ? 'No' : 'Yes')) ?></li>
						<?php } ?>
					</ul>
				</td>
				<td>
					<ul class="plain">
						<?php foreach($mod['new']->list_comments() as $comment) { ?>
						<li><?php out($comment->content) ?></li>
						<?php } ?>
					</ul>
				</td>
				<td><input type="checkbox" name="updates[]"<?php out($checked)?> value="<?php out($mod['json']) ?>"></td>
			</tr>
			<?php } ?>
		</tbody>
	</table>
	<?php } ?>
	<?php if(count($modifications['update']) > 0) { ?>
	<h2>Updated resource recordsets</h2>
	<table class="table table-bordered changepreview">
		<thead>
			<tr>
				<th class="name">Name</th>
				<th class="type">Type</th>
				<th class="ttl">TTL</th>
				<th>Changes</th>
				<th class="confirm">Okay to update?</th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach($modifications['update'] as $mod) {
				$count++;
				if($count > $limit) $checked = '';
				?>
			<tr>
				<td><?php out(DNSName::abbreviate($mod['new']->name, $zone->name))?></td>
				<td><?php out($mod['new']->type)?></td>
				<td><?php out(DNSTime::abbreviate($mod['new']->ttl))?></td>
				<td>
					<ul class="plain">
						<?php foreach($mod['changelist'] as $change) { ?>
						<li><?php out($change) ?></li>
						<?php } ?>
					</ul>
				</td>
				<td><input type="checkbox" name="updates[]"<?php out($checked)?> value="<?php out($mod['json']) ?>"></td>
			</tr>
			<?php } ?>
		</tbody>
	</table>
	<?php } ?>
	<?php if(count($modifications['delete']) > 0) { ?>
	<h2>Deleted resource recordsets</h2>
	<table class="table table-bordered changepreview">
		<thead>
			<tr>
				<th class="name">Name</th>
				<th class="type">Type</th>
				<th class="ttl">TTL</th>
				<th>Data</th>
				<th>Comments</th>
				<th class="confirm">Okay to delete?</th>
			</tr>
		</thead>
		<tbody>
			<?php
			foreach($modifications['delete'] as $mod) {
				$count++;
				if($count > $limit) $checked = '';
				?>
			<tr>
				<td><?php out(DNSName::abbreviate($mod['old']->name, $zone->name))?></td>
				<td><?php out($mod['old']->type)?></td>
				<td><?php out(DNSTime::abbreviate($mod['old']->ttl))?></td>
				<td>
					<ul class="plain">
						<?php foreach($mod['old']->list_resource_records() as $rr) { ?>
						<li><?php out('Content: '.$rr->content) ?></li>
						<?php } ?>
					</ul>
				</td>
				<td>
					<ul class="plain">
						<?php foreach($mod['old']->list_comments() as $comment) { ?>
						<li><?php out($comment->content) ?></li>
						<?php } ?>
					</ul>
				</td>
				<td><input type="checkbox" name="updates[]"<?php out($checked)?> value="<?php out($mod['json']) ?>"></td>
			</tr>
			<?php } ?>
		</tbody>
	</table>
	<?php } ?>
	<?php if($checked == '') { ?>
	<p class="alert alert-danger">
		By default only the first <?php out($limit)?> changes (out of <?php out($count)?>) have been selected for import as larger imports may be rejected by PowerDNS.
		It is recommended that you run this import multiple times until all changes have been imported.
	</p>
	<?php } ?>
	<p>
		<button type="submit" name="update_rrs" value="1" class="btn btn-primary">Confirm selected changes</button>
		<a href="/zones/<?php out(DNSZoneName::unqualify($zone->name), ESC_URL)?>" class="btn btn-default">Cancel import</a>
	</p>
</form>
<?php } ?>
