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
?>
<form method="post" action="<?php outurl('/login')?>" class="form-horizontal">
	<fieldset>
		<legend>Login</legend>
			<div class="form-group">
				<label for="name" class="col-sm-2 control-label">User</label>
				<div class="col-sm-10">
					<input type="text" class="form-control authbox" id="username" name="username" required pattern="\S+" maxlength="255" value="">
				</div>
			</div>

			<div class="form-group">
				<label for="name" class="col-sm-2 control-label">Password</label>
				<div class="col-sm-10">
					<input type="password" class="form-control authbox" id="password" name="password" required pattern="\S+" maxlength="255" value="">
				</div>
			</div>

			<div class="form-group">
			<div class="col-sm-offset-2 col-sm-10">
				<button type="submit" class="btn btn-primary" name="update_settings" value="1">Login</button>
			</div>
		</div>
	</fieldset>
</form>
