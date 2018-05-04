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
$replication_types = $this->get('replication_types');
$ns_templates = $this->get('ns_templates');
$soa_templates = $this->get('soa_templates');
?>
<h1>Settings</h1>
<form method="post" action="<?php outurl('/settings')?>" class="form-horizontal">
	<fieldset>
		<legend>Defaults (new zone)</legend>
		<p class="alert alert-info">When creating a new zone, these settings will be used to pre-fill the form.</p>
		<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
		<div class="form-group">
			<label for="default_replication_type" class="col-sm-2 control-label">Replication type</label>
			<div class="col-sm-10">
				<?php foreach($replication_types as $type) { ?>
				<div class="radio">
					<label>
						<input type="radio" name="default_replication_type" value="<?php out($type->id)?>"<?php if($type->default) out(' checked')?>>
						<?php out($type->name)?>&mdash;<?php out($type->description)?>
					</label>
				</div>
				<?php } ?>
			</div>
		</div>
		<div class="form-group">
			<label for="default_soa_template" class="col-sm-2 control-label">SOA template</label>
			<div class="col-sm-10">
				<select class="form-control" name="default_soa_template">
					<option value="">No default</option>
					<?php foreach($soa_templates as $template) { ?>
					<option value="<?php out($template->id)?>"<?php if($template->default) out(' selected')?>><?php out($template->name)?></option>
					<?php } ?>
				</select>
			</div>
		</div>
		<div class="form-group">
			<label for="default_ns_template" class="col-sm-2 control-label">NS template</label>
			<div class="col-sm-10">
				<select class="form-control" name="default_ns_template">
					<option value="">No default</option>
					<?php foreach($ns_templates as $template) { ?>
					<option value="<?php out($template->id)?>"<?php if($template->default) out(' selected')?>><?php out($template->name)?></option>
					<?php } ?>
				</select>
			</div>
		</div>
		<div class="form-group">
			<div class="col-sm-offset-2 col-sm-10">
				<button type="submit" class="btn btn-primary" name="update_settings" value="1">Update settings</button>
			</div>
		</div>
	</fieldset>
</form>