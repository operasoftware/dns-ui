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
?>
<h2>Zone split of <?php out(idn_to_utf8($newzonename, 0, INTL_IDNA_VARIANT_UTS46))?> from <?php out(idn_to_utf8($zone->name, 0, INTL_IDNA_VARIANT_UTS46))?></h2>
<ul>
	<li><a href="/zones/<?php out(urlencode($zone->name))?>">View <?php out(idn_to_utf8($zone->name, 0, INTL_IDNA_VARIANT_UTS46))?> zone</a></li>
	<li><a href="/zones/<?php out(urlencode($newzonename))?>">View <?php out(idn_to_utf8($newzonename, 0, INTL_IDNA_VARIANT_UTS46))?> zone</a></li>
</ul>
