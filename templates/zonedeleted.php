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
$active_user = $this->get('active_user');
$zone = $this->get('zone');
$deletion = $this->get('deletion');
?>
<h2 class="text-danger">Zone <?php out(DNSZoneName::unqualify(punycode_to_utf8($zone->name)))?> does not exist</h2>
<p>This zone no longer exists on the DNS server.</p>
<?php if(!is_null($deletion) && !is_null($deletion['confirm_date'])) { ?>
<dl class="dl-horizontal">
	<dt>Deletion requested by</dt>
	<dd><a href="/users/<?php out($deletion['requester']->uid, ESC_URL)?>"><?php out($deletion['requester']->name)?></a> on <?php out($deletion['request_date']->format('Y-m-d H:i:s'))?></dd>
	<dt>Deletion confirmed by</dt>
	<dd><a href="/users/<?php out($deletion['confirmer']->uid, ESC_URL)?>"><?php out($deletion['confirmer']->name)?></a> on <?php out($deletion['confirm_date']->format('Y-m-d H:i:s'))?></dd>
</dl>
<h3>Zone archive</h3>
<p>This is a snapshot of the zone's contents prior to its deletion.</p>
<pre class="source"><?php out($deletion['zone_export'])?></pre>
<form method="post" action="/zones/<?php out(DNSZoneName::unqualify($zone->name), ESC_URL)?>" class="zonerestore form-inline">
	<?php out($this->get('active_user')->get_csrf_field(), ESC_NONE) ?>
	<div class="checkbox"><label><input type="checkbox" name="restore_zone" value="1"> Confirm zone restore</label></div>
	<button type="submit" class="btn btn-danger">Restore zone from archive<span>…</span></button>
</form>
<?php } ?>
