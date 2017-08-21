<?php
$migration_name = "Add migration support";

$this->database->exec("
CREATE TABLE migration (
    id integer NOT NULL,
    name text NOT NULL,
    applied timestamp without time zone NOT NULL,
    CONSTRAINT migration_pkey PRIMARY KEY (id)
) WITH (OIDS=FALSE)
");
