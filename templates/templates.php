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
$templates['soa'] = $this->get('soa_templates');
$templates['ns'] = $this->get('ns_templates');
$type = $this->get('type');
$types = array('soa', 'ns');
?>
<h1><?php out($this->get('title'))?></h1>
<p>These templates are used when creating new zones to pre-populate the form fields. Any number of preset templates can be defined below. If a default is selected, its values will be pre-filled without user interaction.</p>
<?php if(!is_null($type)) { ?>
<ul class="nav nav-tabs" role="tablist">
	<li role="presentation" class="active"><a href="#list" aria-controls="list" role="tab" data-toggle="tab">Template list</a></li>
	<li role="presentation"><a href="#create" aria-controls="create" role="tab" data-toggle="tab">Create template</a></li>
</ul>
<?php } ?>
<div class="tab-content">
	<div role="tabpanel" class="tab-pane active" id="list">
		<h2 class="sr-only">Forward zones</h2>
		<form method="post" action="<?php out($this->get('relative_request_url'))?>">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<table class="table table-bordered">
				<thead>
					<tr>
						<?php if(is_null($type)) { ?>
						<th>Type</th>
						<?php } ?>
						<th>Template name</th>
						<th>Actions</th>
					</tr>
				</thead>
				<tbody>
					<?php
					foreach($types as $t) {
						if(is_null($type) || $type == $t) {
							foreach($templates[$t] as $template) {
					?>
					<tr>
						<?php if(is_null($type)) { ?>
						<td><?php out(strtoupper($t))?></td>
						<?php } ?>
						<td><a href="/templates/<?php out($t)?>/<?php out($template->name, ESC_URL)?>"><?php out($template->name)?></a></td>
						<td>
							<a href="/templates/<?php out($t)?>/<?php out($template->name, ESC_URL)?>" class="btn btn-xs btn-default"><span class="glyphicon glyphicon-cog"></span> Edit</a>
							<button type="submit" class="btn btn-xs btn-default" name="delete_<?php out($t)?>_template" value="<?php out($template->id)?>"><span class="glyphicon glyphicon-trash"></span> Delete</button>
							<?php if($template->default) { ?>
							<button type="submit" class="btn btn-xs btn-success" disabled>Default</button>
							<?php } else { ?>
							<button type="submit" class="btn btn-xs btn-default" name="set_default_<?php out($t)?>_template" value="<?php out($template->id)?>">Set as default</button>
							<?php } ?>
						</td>
					</tr>
					<?php
							}
						}
					}
					?>
				</tbody>
			</table>
		</form>
	</div>
	<?php if(!is_null($type)) { ?>
	<div role="tabpanel" class="tab-pane" id="create">
		<form method="post" action="/templates/<?php out($type, ESC_URL)?>" class="form-horizontal">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<div class="form-group">
				<label for="name" class="col-sm-2 control-label">Template name</label>
				<div class="col-sm-10">
					<input type="text" class="form-control" id="name" name="name" value="" required>
				</div>
			</div>
			<?php if($type == 'soa') { ?>
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
			<?php } elseif($type == 'ns') { ?>
			<div class="form-group">
				<label for="nameservers" class="col-sm-2 control-label">Nameservers</label>
				<div class="col-sm-10">
					<textarea class="form-control" id="nameservers" name="nameservers" rows="3"></textarea>
				</div>
			</div>
			<?php } ?>
			<div class="form-group">
				<div class="col-sm-offset-2 col-sm-10">
					<button type="submit" class="btn btn-primary" name="create_template" value="1">Create template</button>
				</div>
			</div>
		</form>
	</div>
	<?php } ?>
</div>
