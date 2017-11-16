<?php
$migration_name = 'Add dnssec support';

// Add 'dnssec' field to zone table
$this->database->exec('
ALTER TABLE ONLY zone
	ADD dnssec integer;
');
