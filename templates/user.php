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
$user = $this->get('user');
$changesets = $this->get('changesets');
global $output_formatter;
?>
<h1><span class="glyphicon glyphicon-user" title="User"></span> <?php out($user->name)?> <small>(<?php out($user->uid)?>)</small></h1>
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
			<td class="nowrap"><a href="/zones/<?php out($changeset->zone->name)?>"><?php out(punycode_to_utf8($changeset->zone->name))?></a></td>
			<td><?php out('-'.$changeset->deleted.'/+'.$changeset->added)?></td>
			<td></td>
		</tr>
		<?php } ?>
	</tbody>
</table>
<?php } ?>
