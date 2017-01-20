Opera DNS UI
============

A tool to manage a PowerDNS authoritative server in a corporate LDAP-driven environment.

Features
--------

* Connects to PowerDNS via its JSON API.
* Allows login managed by LDAP server.
* Create zones; add, edit and delete records.
* Grant multiple users access to administer a zone.
* Lower access level that allows to view a zone and *request* changes.
* Provides its own JSON API for making changes to DNS records.
* Keeps a changelog of all DNS changes done through it.
* (Optionally) export all zones as bind-format zone files and store changes in git.

Compatibility
-------------

The current version is only compatible with the experimental JSON API of PowerDNS 3.

A branch exists that implements full PowerDNS 4 support, but PowerDNS 4 has [a serious API
bug](https://github.com/PowerDNS/pdns/issues/4766) that greatly affects functionality of the DNS UI and can lead to data
corruption.

Requirements
------------

* Apache 2.2 or higher
* PHP 5.6 or higher
* PHP intl (Internationalization Functions) extension
* PHP JSON extension
* PHP LDAP extension
* PHP PDO_PGSQL extension
* PostgreSQL database
* PowerDNS authoritative server (>= 3.4.2, < 4.0)

Installation
------------

1.  Configure PowerDNS:

        webserver-address=...
        webserver-allow-from=...
        webserver-port=...
        experimental-json-interface=yes
        experimental-api-key=...

2.  Clone this repo to somewhere *outside* of your default Apache document root.

3.  Create a postgresql user and database.

        createuser -P dnsui-user
        createdb -O dnsui-user dnsui-db

4.  Import the database schema from `schema.sql`:

        psql -U dnsui-user dnsui-db < schema.sql

5.  Add the following directives to your Apache configuration (eg. virtual host config):

        DocumentRoot /path/to/dnsui/public_html
        DirectoryIndex init.php
        FallbackResource /init.php

6.  Set up authnz_ldap for your virtual host (or any other authentication module that will pass on an Auth-user
    variable to the application).

7.  Copy the file `config/config-sample.ini` to `config/config.ini` and edit the settings as required.

8.  Set `scripts/ldap_update.php` to run on a regular cron job.

Usage
-----

Anyone in the LDAP group defined under `admin_group_cn` in `config/config.ini` will be able to add and modify all zones.
They will also be able to grant access under "User access" for any zone to any number of users.

License
-------

Copyright 2013-2017 Opera Software

Licensed under the Apache License, Version 2.0 (the "License");
you may not use this file except in compliance with the License.
You may obtain a copy of the License at

   http://www.apache.org/licenses/LICENSE-2.0

Unless required by applicable law or agreed to in writing, software
distributed under the License is distributed on an "AS IS" BASIS,
WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
See the License for the specific language governing permissions and
limitations under the License.