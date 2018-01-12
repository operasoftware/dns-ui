<?php
$migration_name = 'Add zone delete table';

// Add replication type table with standard entries
$this->database->exec('
CREATE TABLE zone_delete (
    zone_id integer NOT NULL,
    requester_id integer NOT NULL,
    confirmer_id integer,
    request_date timestamp without time zone NOT NULL,
    confirm_date timestamp without time zone,
    zone_export TEXT,
	CONSTRAINT zone_delete_pkey PRIMARY KEY (zone_id),
	CONSTRAINT zone_delete_zone_id_fkey FOREIGN KEY (zone_id) REFERENCES zone(id) ON DELETE CASCADE,
	CONSTRAINT zone_delete_requester_id_fkey FOREIGN KEY (requester_id) REFERENCES "user"(id),
	CONSTRAINT zone_delete_confirmer_id_fkey FOREIGN KEY (confirmer_id) REFERENCES "user"(id)
) WITH (OIDS=FALSE)
');
