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
$user = $this->get('user');
$changesets = $this->get('changesets');
global $output_formatter;
?>
<h1><span class="glyphicon glyphicon-user" title="User"></span> <?php out($user->name)?> <small>(<?php out($user->uid)?>)</small></h1>
<h2>User details</h2>
<?php if($active_user->admin && $user->auth_realm === 'local') { ?>
<form method="post" action="/users/<?php out($user->uid, ESC_URL)?>" class="form-horizontal">
	<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
	<div class="form-group">
		<label for="uid" class="col-sm-2 control-label">User ID</label>
		<div class="col-sm-10">
			<p class="form-control-static"><?php out($user->uid)?></p>
		</div>
	</div>
	<div class="form-group">
		<label for="name" class="col-sm-2 control-label">Full name</label>
		<div class="col-sm-10">
			<input type="text" class="form-control" id="name" name="name" required pattern=".*\S+.*" maxlength="255" value="<?php out($user->name)?>">
		</div>
	</div>
	<div class="form-group">
		<label for="email" class="col-sm-2 control-label">Email address</label>
		<div class="col-sm-10">
			<input type="email" class="form-control" id="email" name="email" required maxlength="255" value="<?php out($user->email)?>">
		</div>
	</div>
	<div class="form-group">
		<label class="col-sm-2 control-label">Status</label>
		<div class="col-sm-10">
			<div class="checkbox">
				<label><input type="checkbox" id="active" name="active"<?php if($user->active) out(' checked')?>>Active</label>
			</div>
		</div>
	</div>
	<div class="form-group">
		<label class="col-sm-2 control-label">Roles</label>
		<div class="col-sm-10">
			<div class="checkbox">
				<label><input type="checkbox" id="admin" name="admin"<?php if($user->admin) out(' checked')?>>Administrator</label>
			</div>
		</div>
	</div>
	<div class="form-group">
		<div class="col-sm-offset-2 col-sm-10">
			<button type="submit" class="btn btn-primary" name="update_user" value="1">Update user</button>
		</div>
	</div>
</form>
<?php } else { ?>
<dl class="dl-horizontal">
	<dt>User ID</dt>
	<dd><?php out($user->uid)?></dd>
	<dt>Full name</dt>
	<dd><?php out($user->name)?></dd>
	<dt>Email address</dt>
	<dd><?php out($user->email)?></dd>
</dl>
<?php } ?>
<h2>Activity</h2>
<?php if(count($changesets) == 0) { ?>
<p>No activity yet.</p>
<?php } else { ?>
<table class="table table-condensed table-hover changelog">
	<thead>
		<tr>
			<th>Date / time</th>
			<th>Comment</th>
			<th>Requester</th>
			<th>Zone</th>
			<th>Changes</th>
			<th></th>
		</tr>
	</thead>
	<tbody>
		<?php foreach($changesets as $changeset) { ?>
		<tr data-zone="<?php out($changeset->zone->name)?>" data-changeset="<?php out($changeset->id)?>">
			<td class="nowrap"><?php out($changeset->change_date->format('Y-m-d H:i:s'))?></td>
			<td><?php out($output_formatter->changeset_comment_format($changeset->comment), ESC_NONE) ?></td>
			<td class="nowrap"><?php if($changeset->requester) { ?><a href="/users/<?php out($changeset->requester->uid)?>"><?php out($changeset->requester->name)?><?php } ?></td>
			<td class="nowrap"><a href="/zones/<?php out(DNSZoneName::unqualify($changeset->zone->name), ESC_URL)?>"><?php out(punycode_to_utf8(DNSZoneName::unqualify($changeset->zone->name)))?></a></td>
			<td><?php out('-'.$changeset->deleted.'/+'.$changeset->added)?></td>
			<td></td>
		</tr>
		<?php } ?>
	</tbody>
</table>
<?php } ?>
