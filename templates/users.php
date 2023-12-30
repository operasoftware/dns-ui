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
$users = $this->get('users');
?>
<h1>Users</h1>
<ul class="nav nav-tabs" role="tablist">
	<li role="presentation" class="active"><a href="#list" aria-controls="forward" role="tab" data-toggle="tab">User list</a></li>
	<li role="presentation"><a href="#create" aria-controls="create" role="tab" data-toggle="tab">Create user</a></li>
</ul>
<div class="tab-content">
	<div role="tabpanel" class="tab-pane active" id="list">
		<h2 class="sr-only">User list</h2>
		<table class="table table-bordered">
			<thead>
				<tr>
					<th>User ID</th>
					<th>Full name</th>
					<th>Email address</th>
					<th>Directory</th>
					<th>Active</th>
					<th>Admin</th>
				</tr>
			</thead>
			<tbody>
				<?php foreach($users as $user) { ?>
				<tr<?php if(!$user->active) out(' class="text-muted"', ESC_NONE) ?>>
					<td><a href="<?php outurl('/users/'.urlencode($user->uid))?>" class="user<?php if(!$user->active) out(' text-muted') ?>"><?php out($user->uid)?></a></td>
					<td><?php out($user->name)?></td>
					<td><?php out($user->email)?></td>
					<td><?php out(ucfirst($user->auth_realm ?? ''))?></td>
					<td><?php out($user->active ? '✓' : '')?></td>
					<td><?php out($user->admin ? '✓' : '')?></td>
				</tr>
				<?php } ?>
			</tbody>
		</table>
	</div>
	<div role="tabpanel" class="tab-pane" id="create">
		<h2 class="sr-only">Create user</h2>
		<p class="alert alert-info">You can create users in the local directory here. It is not possible to create users in your LDAP directory from the DNS UI.</p>
		<form method="post" action="<?php outurl('/users#create')?>" class="form-horizontal">
			<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
			<div class="form-group">
				<label for="uid" class="col-sm-2 control-label">User ID</label>
				<div class="col-sm-10">
					<input type="text" class="form-control" id="uid" name="uid" required pattern=".*\S+.*" maxlength="255">
				</div>
			</div>
			<div class="form-group">
				<label for="name" class="col-sm-2 control-label">Full name</label>
				<div class="col-sm-10">
					<input type="text" class="form-control" id="name" name="name" required pattern=".*\S+.*" maxlength="255">
				</div>
			</div>
			<div class="form-group">
				<label for="email" class="col-sm-2 control-label">Email address</label>
				<div class="col-sm-10">
					<input type="email" class="form-control" id="email" name="email" required maxlength="255">
				</div>
			</div>
			<div class="form-group">
				<label class="col-sm-2 control-label">Roles</label>
				<div class="col-sm-10">
					<div class="checkbox">
						<label><input type="checkbox" id="admin" name="admin">Administrator</label>
					</div>
				</div>
			</div>
			<div class="form-group">
				<div class="col-sm-offset-2 col-sm-10">
					<button type="submit" class="btn btn-primary" name="add_user" value="1">Create local user</button>
				</div>
			</div>
		</form>
	</div>
</div>
