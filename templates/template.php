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
$type = $this->get('type');
$template = $this->get('template');
?>
<h1><a href="<?php outurl('/templates/'.urlencode($type))?>"><?php out(strtoupper($type))?> templates</a>: <?php out($template->name)?></h1>
<form method="post" action="<?php outurl('/templates/'.urlencode($type).'/'.urlencode($template->name))?>" class="form-horizontal">
	<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
	<div class="form-group">
		<label for="name" class="col-sm-2 control-label">Template name</label>
		<div class="col-sm-10">
			<input type="text" class="form-control" id="name" name="name" value="<?php out($template->name)?>" required>
		</div>
	</div>
	<?php if($type == 'soa') { ?>
	<div class="form-group">
		<label for="primary_ns" class="col-sm-2 control-label">Primary nameserver</label>
		<div class="col-sm-10">
			<input type="text" class="form-control" id="primary_ns" name="primary_ns" value="<?php out($template->primary_ns)?>" required pattern="\S+">
		</div>
	</div>
	<div class="form-group">
		<label for="contact" class="col-sm-2 control-label">Contact</label>
		<div class="col-sm-10">
			<input type="text" class="form-control" id="contact" name="contact" value="<?php out($template->contact)?>" required pattern="\S+">
		</div>
	</div>
	<div class="form-group">
		<label for="refresh" class="col-sm-2 control-label">Refresh</label>
		<div class="col-sm-10">
			<input type="text" class="form-control" id="refresh" name="refresh" value="<?php out(DNSTime::abbreviate($template->refresh))?>" required pattern="([0-9]+[smhdwSMHDW]?)+">
		</div>
	</div>
	<div class="form-group">
		<label for="retry" class="col-sm-2 control-label">Retry</label>
		<div class="col-sm-10">
			<input type="text" class="form-control" id="retry" name="retry" value="<?php out(DNSTime::abbreviate($template->retry))?>" required pattern="([0-9]+[smhdwSMHDW]?)+">
		</div>
	</div>
	<div class="form-group">
		<label for="expire" class="col-sm-2 control-label">Expire</label>
		<div class="col-sm-10">
			<input type="text" class="form-control" id="expire" name="expire" value="<?php out(DNSTime::abbreviate($template->expire))?>" required pattern="([0-9]+[smhdwSMHDW]?)+">
		</div>
	</div>
	<div class="form-group">
		<label for="default_ttl" class="col-sm-2 control-label">Default TTL</label>
		<div class="col-sm-10">
			<input type="text" class="form-control" id="default_ttl" name="default_ttl" value="<?php out(DNSTime::abbreviate($template->default_ttl))?>" required pattern="([0-9]+[smhdwSMHDW]?)+">
		</div>
	</div>
	<div class="form-group">
		<label for="soa_ttl" class="col-sm-2 control-label">SOA TTL</label>
		<div class="col-sm-10">
			<input type="text" class="form-control" id="soa_ttl" name="soa_ttl" value="<?php out(DNSTime::abbreviate($template->soa_ttl))?>" required pattern="([0-9]+[smhdwSMHDW]?)+">
		</div>
	</div>
	<?php } elseif($type == 'ns') { ?>
	<div class="form-group">
		<label for="nameservers" class="col-sm-2 control-label">Nameservers</label>
		<div class="col-sm-10">
			<textarea class="form-control" id="nameservers" name="nameservers" rows="3"><?php out($template->nameservers)?></textarea>
		</div>
	</div>
	<?php } ?>
	<div class="form-group">
		<div class="col-sm-offset-2 col-sm-10">
			<button type="submit" class="btn btn-primary" name="update_template" value="1">Update template</button>
		</div>
	</div>
</form>
