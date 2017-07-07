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
?>
<h1>DNS server communication failure</h1>
<p>The DNS server is not responding.</p>
<?php if(!is_null($active_user) && $active_user->admin) { ?>
<p>Make sure that the following PowerDNS parameters are set correctly in <code>pdns.conf</code>:</p>
<pre>webserver=yes
webserver-address=...
webserver-allow-from=...
webserver-port=...
api=yes
api-key=...</pre>
<p>Reload PowerDNS after making changes to this file.</p>
<p>Also check the values set in the <code>[powerdns]</code> section of the DNS UI configuration file (<code>config/config.ini</code>).</p>
<?php } ?>
