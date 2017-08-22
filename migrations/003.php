<?php
$migration_name = 'Add replication type';

// Add replication type table with standard entries
$this->database->exec('
CREATE TABLE replication_type (
    id serial,
    name text NOT NULL,
    description text,
    CONSTRAINT replication_type_pkey PRIMARY KEY (id),
    CONSTRAINT replication_type_name_key UNIQUE (name)
) WITH (OIDS=FALSE)
');
$this->database->exec("INSERT INTO replication_type VALUES (1, 'Native', 'Native replication means that PowerDNS will not send out DNS update notifications, nor will react to them. PowerDNS assumes that the backend is taking care of replication unaided.')");
$this->database->exec("INSERT INTO replication_type VALUES (2, 'Master', 'When operating as a master, PowerDNS sends out notifications of changes to slaves, which react to these notifications by querying PowerDNS to see if the zone changed, and transferring its contents if it has.')");

// Due to a mistake, the config.default_soa_template field was originally created without a foreign key constraint.
// We add it below but first we need to ensure that the existing data will not violate the constraint.
$stmt = $this->database->prepare('
SELECT config.default_soa_template, soa_template.id
FROM config
LEFT JOIN soa_template ON soa_template.id = config.default_soa_template
');
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if(is_null($row['id']) && !is_null($row['default_soa_template'])) {
	// config points to an SOA template that doesn't exist any more - reset to null
	$this->database->exec('UPDATE config SET default_soa_template = NULL');
}

// Add new default_replication_type field to config table and also add missing constraint on default_soa_template
$this->database->exec('
ALTER TABLE ONLY config
	ADD default_replication_type integer,
	ADD CONSTRAINT config_default_replication_type_fkey FOREIGN KEY (default_replication_type) REFERENCES replication_type(id) ON DELETE SET NULL,
	ADD CONSTRAINT config_default_soa_template_fkey FOREIGN KEY (default_soa_template) REFERENCES soa_template(id) ON DELETE SET NULL;
');
// Maintain previous behaviour by setting Master as default replication type
$this->database->exec("UPDATE config SET default_replication_type = 2");

// Add 'kind' field to zone table
$this->database->exec('
ALTER TABLE ONLY zone
	ADD kind text;
');
