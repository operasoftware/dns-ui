<?php
$migration_name = 'Add catalog zone support';

$this->database->exec('ALTER TABLE ONLY zone ADD catalog text;');

$this->database->exec("INSERT INTO replication_type VALUES (3, 'Producer', 'A producer or catalog zone is a special zone that store the current list of zones assoicated with it. It can be used by secondary servers to update the list of zones for which they are authoritative')");

$this->database->exec("INSERT INTO soa_template VALUES (1, 'Producer', 'invalid.', 'invalid.', 3600, 600, 2147483646, 0, 0)");
$this->database->exec("INSERT INTO ns_template VALUES (1, 'Producer', 'invalid.')");
