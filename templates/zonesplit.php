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
$newzonename = $this->get('newzonename');
$suffix = $this->get('suffix');
$split = $this->get('split');
$cname_error = $this->get('cname_error');
?>
<h2>Zone split of <?php out(punycode_to_utf8(DNSZoneName::unqualify($newzonename)))?> from <?php out(punycode_to_utf8(DNSZoneName::unqualify($zone->name)))?></h2>
<form method="post" action="/zones/<?php out(DNSZoneName::unqualify($zone->name), ESC_URL)?>/split">
	<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
	<?php if(count($split) == 0) { ?>
	<p>No records match this pattern.</p>
	<p><a href="/zones/<?php out($zone->name, ESC_URL)?>" class="btn btn-default">Go back</a></p>
	<?php } else { ?>
	<table class="table table-bordered table-condensed table-hover stickyHeader">
		<thead>
			<tr>
				<th>Name</th>
				<th>New name</th>
				<th>Type</th>
				<th>TTL</th>
				<th>Content</th>
				<th>Enabled</th>
			</tr>
		</thead>
		<tbody>
			<?php
			$rrsetnum = 0;
			foreach($split as $rrset) {
				if($rrset->type == 'NS') continue;
				$rrsetnum++;
				$rrs = $rrset->list_resource_records();
				$name = DNSName::abbreviate($rrset->name, $zone->name);
				$newname = DNSName::abbreviate($rrset->name, $newzonename);
				$rowclasses = array();
				$firstrow = reset($rrs);
				if($firstrow->disabled) $rowclasses[] = 'disabled';
				if($newname == '@' && $rrset->type == 'CNAME') $rowclasses[] = 'danger';
				?>
			<tr class="<?php out(implode(' ', $rowclasses))?>">
				<td class="align-right nowrap" rowspan="<?php out(count($rrs))?>"><strong><?php out(punycode_to_utf8($name))?></strong><span class="text-muted">.<?php out(punycode_to_utf8(DNSZoneName::unqualify($zone->name)))?></span></td>
				<td class="align-right nowrap" rowspan="<?php out(count($rrs))?>"><strong><?php out(punycode_to_utf8($newname))?></strong><span class="text-muted">.<?php out(punycode_to_utf8(DNSZoneName::unqualify($newzonename)))?></span></td>
				<td rowspan="<?php out(count($rrs))?>"><?php out($rrset->type)?></td>
				<?php
				$count = 0;
				foreach($rrs as $rr) {
					$rowclasses = array();
					if($rr->disabled) $rowclasses[] = 'disabled';
					$count++;
					if($count > 1) {
						out('</tr><tr', ESC_NONE);
						if(count($rowclasses) > 0) {
							out(' class="'.hesc(implode(' ', $rowclasses)).'"', ESC_NONE);
						}
						out('>', ESC_NONE);
					}
					$rr->content = DNSContent::decode($rr->content, $rrset->type, $zone->name);
					?>
				<td><?php out(DNSTime::abbreviate($rr->ttl))?></td>
				<td><?php out($rr->content)?></td>
				<td><?php out($rr->disabled ? 'No' : 'Yes')?></td>
					<?php
				}
				?>
			</tr>
			<?php } ?>
		</tbody>
	</table>
	<?php if(!$cname_error) { ?>
	<div class="form-group">
		<label for="comment">Change comment</label>
		<input type="text" id="comment" name="comment" class="form-control">
	</div>
	<?php } ?>
	<div class="form-group">
		<input type="hidden" name="suffix" value="<?php out($suffix)?>">
		<?php if(!$cname_error) { ?>
		<button type="submit" name="confirm" value="1" class="btn btn-primary">Split records into new zone</button>
		<?php } ?>
		<a href="/zones/<?php out(DNSZoneName::unqualify($zone->name), ESC_URL)?>" class="btn btn-default">Cancel</a>
	</div>
	<?php } ?>
</form>
