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
<h1>API documentation</h1>
<h2>Table of contents</h2>
<ul>
	<li>
		<a href="#zones">Zones</a>
		<ul>
			<li><a href="#zones-get">GET /api/v2/zones</a></li>
			<li><a href="#zones-name-get">GET /api/v2/zones/{name}</a></li>
			<li><a href="#zones-name-patch">PATCH /api/v2/zones/{name}</a></li>
			<li><a href="#zones-name-changes-get">GET /api/v2/zones/{name}/changes</a></li>
			<li><a href="#zones-name-changes-id-get">GET /api/v2/zones/{name}/changes/{id}</a></li>
		</ul>
	</li>
</ul>
<h2 id="zones">Zones</h2>
<h3 id="zones-get">GET /api/v2/zones</h3>
<p>Returns an array of all zones.</p>
<h4>Example</h4>
<pre>GET /api/v2/zones</pre>
<h5>Response content</h5>
<?php syntax_highlight('[
    {
        "name": "1.0.0.0.8.b.d.0.1.0.0.2.ip6.arpa.",
        "serial": 2016021000
    },
    {
        "name": "2.0.192.in-addr.arpa.",
        "serial": 2016022301
    },
    {
        "name": "example.com.",
        "serial": 2016020200
    },
    {
        "name": "xn--n8j843nygt.com.",
        "serial": 2016032204
    }
]', 'javascript')?>
<!--
<h3>POST /api/v2/zones</h3>
<p>Creates a new zone.</p>
<h4>Example</h4>
<pre><code>[
]</code></pre>
-->
<h3 id="zones-name-get">GET /api/v2/zones/{name}</h3>
<p>Returns all data regarding the named zone.</p>
<h4>Example</h4>
<pre>GET /api/v2/zones/example.com.</pre>
<h5>Response content</h5>
<?php syntax_highlight('{
    "name": "example.com.",
    "serial": 2016020200,
    "rrsets": [
        {
            "name": "@",
            "type": "SOA",
            "ttl": "1H",
            "records": [
                {
                    "content": "ns.example.net. hostmaster.example.com. 2016020200 28800 7200 604800 86400",
                    "enabled": true
                }
            ],
            "comments": []
        },
        {
            "name": "@",
            "type": "NS",
            "ttl": "1H",
            "records": [
                {
                    "content": "ns1.example.net.",
                    "enabled": true
                },
                {
                    "content": "ns2.example.net.",
                    "enabled": true
                }
            ],
            "comments": []
        },
        {
            "name": "record-1",
            "type": "A",
            "ttl": "1H",
            "records": [
                {
                    "content": "192.0.2.1",
                    "enabled": true
                }
            ],
            "comments": [
                {
                    "content": "",
                    "account": "thomasp",
                    "modified_at": 1464018476
                }
            ]
        },
        {
            "name": "record3",
            "type": "AAAA",
            "ttl": "1H",
            "records": [
                {
                    "content": "2001:db8:3000:620:107:167:104:10",
                    "enabled": true
                }
            ],
            "comments": [
                {
                    "content": "",
                    "account": "thomasp",
                    "modified_at": 1464018638
                }
            ]
        }
    ]
}', 'javascript')?>
<h3 id="zones-name-patch">PATCH /api/v2/zones/{name}</h3>
<p>Update recordsets in the named zone. Multiple actions can be performed in a single request, of which there are 3 types:</p>
<ul>
	<li><code>add</code> - create a new resource recordset</li>
	<li><code>update</code> - modify an existing resource recordset</li>
	<li><code>delete</code> - remove an existing resource recordset</li>
</ul>
<h4>Example</h4>
<pre>PATCH /api/v2/zones/example.com.</pre>
<h5>Request content</h5>
<?php syntax_highlight('{
	"actions": [
		{
			"action": "add",
			"name": "record2",
			"type": "A",
			"ttl": "1D",
			"comment": "Issue 1234",
			"records": [
				{
					"content": "10.10.10.10",
					"enabled": true
				}
			]
		},
		{
			"action": "update",
			"oldname": "record-1",
			"oldtype": "A",
			"name": "record1",
			"type": "A",
			"ttl": "1H",
			"comment": "Issue 1232",
			"records": [
				{
					"content": "192.0.2.1",
					"enabled": true
				},
				{
					"content": "192.0.2.2",
					"enabled": true
				}
			]
		}
		{
			"action": "delete",
			"name": "record3",
			"type": "CNAME"
		}
	],
	"comment": "A comment for this update"
}', 'javascript')?>
<h3 id="zones-name-changes-get">GET /api/v2/zones/{name}/changes</h3>
<p>Returns all changelog entries for the named zone.</p>
<h4>Example</h4>
<pre>GET /api/v2/zones/example.com./changes</pre>
<h5>Response content</h5>
<?php syntax_highlight('[
    {
        "id": 207,
        "author_uid": "thomasp",
        "change_date": "2016-05-26T13:46:17+00:00",
        "comment": "API test",
        "deleted": 1,
        "added": 1
    },
    {
        "id": 206,
        "author_uid": "thomasp",
        "change_date": "2016-05-26T13:45:59+00:00",
        "comment": "API test",
        "deleted": 1,
        "added": 1
    }
]', 'javascript')?>
<h3 id="zones-name-changes-id-get">GET /api/v2/zones/{name}/changes/{id}</h3>
<p>Returns full change information for a specific changeset within the named zone.</p>
<h4>Example</h4>
<pre>GET /api/v2/zones/example.com./changes/207</pre>
<h5>Response content</h5>
<?php syntax_highlight('{
    "id": 207,
    "author_uid": "thomasp",
    "change_date": "2016-05-26T13:46:17+00:00",
    "comment": "API test",
    "deleted": 1,
    "added": 1,
    "changes": [
        {
            "before": {
                "name": "record3.example.com.",
                "type": "A",
                "ttl": "1H",
                "rrs": [
                    {
                        "content": "192.0.2.1",
                        "enabled": true
                    }
                ],
                "comment": "API test"
            },
            "after": {
                "name": "record3.example.com.",
                "type": "A",
                "ttl": "1H",
                "rrs": [
                    {
                        "content": "192.0.2.1",
                        "enabled": false
                    }
                ],
                "comment": "API test"
            }
        }
    ]
}', 'javascript')?>
