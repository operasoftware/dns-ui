<?php
$migration_name = 'Initial setup, converted to migration';

// This migration's execution is conditional on the tables not already existing.
// This is because the migration system was not originally in place and admins
// were instructed to manually run an SQL script to create this initial setup.
try {
	$this->database->exec('SELECT * FROM config');
} catch(PDOException $e) {
	$this->database->exec("CREATE TYPE access_level AS ENUM ('administrator', 'operator')");
	$this->database->exec("CREATE TYPE auth_realm AS ENUM ('LDAP', 'local')");

	$this->database->exec('
	CREATE TABLE change (
		id serial,
		changeset_id integer NOT NULL,
		before bytea,
		after bytea,
		CONSTRAINT change_pkey PRIMARY KEY (id)
	) WITH (OIDS=FALSE)
	');

	$this->database->exec('
	CREATE TABLE changeset (
		id serial,
		zone_id integer NOT NULL,
		author_id integer NOT NULL,
		change_date timestamp without time zone NOT NULL,
		comment text,
		deleted integer DEFAULT 0 NOT NULL,
		added integer DEFAULT 0 NOT NULL,
		requester_id integer,
		CONSTRAINT changeset_pkey PRIMARY KEY (id)
	) WITH (OIDS=FALSE)
	');

	$this->database->exec('
	CREATE TABLE config (
		id integer NOT NULL,
		default_soa_template integer,
		default_ns_template integer,
		CONSTRAINT config_pkey PRIMARY KEY (id)
	) WITH (OIDS=FALSE)
	');
	$this->database->exec('INSERT INTO "config" (id) VALUES (1)');

	$this->database->exec('
	CREATE TABLE ns_template (
		id serial,
		name text NOT NULL,
		nameservers text NOT NULL,
		CONSTRAINT ns_template_pkey PRIMARY KEY (id),
		CONSTRAINT ns_template_name_key UNIQUE (name)
	) WITH (OIDS=FALSE)
	');

	$this->database->exec('
	CREATE TABLE pending_update (
		id serial,
		zone_id integer NOT NULL,
		author_id integer,
		request_date timestamp without time zone NOT NULL,
		raw_data bytea NOT NULL,
		CONSTRAINT pending_change_pkey PRIMARY KEY (id)
	) WITH (OIDS=FALSE)
	');

	$this->database->exec('
	CREATE TABLE soa_template (
		id serial,
		name text NOT NULL,
		primary_ns text NOT NULL,
		contact text NOT NULL,
		refresh integer NOT NULL,
		retry integer NOT NULL,
		expire integer NOT NULL,
		default_ttl integer NOT NULL,
		soa_ttl integer,
		CONSTRAINT soa_template_pkey PRIMARY KEY (id),
		CONSTRAINT soa_template_name_key UNIQUE (name)
	) WITH (OIDS=FALSE)
	');

	$this->database->exec('
	CREATE TABLE "user" (
		id serial,
		uid text,
		name text,
		email text,
		auth_realm auth_realm,
		active integer,
		admin integer DEFAULT 0 NOT NULL,
		developer integer DEFAULT 0 NOT NULL,
		csrf_token text,
		CONSTRAINT user_pkey PRIMARY KEY (id),
		CONSTRAINT user_uid_key UNIQUE (uid)
	) WITH (OIDS=FALSE)
	');

	$this->database->exec('
	CREATE TABLE user_alert (
		id serial,
		user_id integer,
		class text,
		content text,
		escaping integer,
		CONSTRAINT user_alert_pkey PRIMARY KEY (id)
	) WITH (OIDS=FALSE)
	');

	$this->database->exec('
	CREATE TABLE zone (
		id serial,
		pdns_id text,
		name text,
		serial bigint,
		active boolean DEFAULT true NOT NULL,
		account text,
		CONSTRAINT zone_pkey PRIMARY KEY (id),
		CONSTRAINT zone_pdns_id_key UNIQUE (pdns_id)
	) WITH (OIDS=FALSE)
	');

	$this->database->exec("
	CREATE TABLE zone_access (
		zone_id integer NOT NULL,
		user_id integer NOT NULL,
		level access_level DEFAULT 'administrator'::access_level NOT NULL,
		CONSTRAINT zone_admin_pkey PRIMARY KEY (zone_id, user_id)
	) WITH (OIDS=FALSE)
	");

	$this->database->exec('
	ALTER TABLE ONLY change
		ADD CONSTRAINT change_changeset_id_fkey FOREIGN KEY (changeset_id) REFERENCES changeset(id) ON DELETE CASCADE;
	');

	$this->database->exec('
	ALTER TABLE ONLY changeset
		ADD CONSTRAINT changeset_author_id_fkey FOREIGN KEY (author_id) REFERENCES "user"(id),
		ADD CONSTRAINT changeset_requester_id_fkey FOREIGN KEY (requester_id) REFERENCES "user"(id),
		ADD CONSTRAINT changeset_zone_id_fkey FOREIGN KEY (zone_id) REFERENCES zone(id) ON DELETE CASCADE;
	');

	$this->database->exec('
	ALTER TABLE ONLY config
		ADD CONSTRAINT config_default_ns_template_fkey FOREIGN KEY (default_ns_template) REFERENCES ns_template(id) ON DELETE SET NULL;
	');

	$this->database->exec('
	ALTER TABLE ONLY pending_update
		ADD CONSTRAINT pending_change_zone_id_fkey FOREIGN KEY (zone_id) REFERENCES zone(id) ON DELETE CASCADE;
	');

	$this->database->exec('
	ALTER TABLE ONLY user_alert
		ADD CONSTRAINT user_alert_user_id_fkey FOREIGN KEY (user_id) REFERENCES "user"(id);
	');

	$this->database->exec('
	ALTER TABLE ONLY zone_access
		ADD CONSTRAINT zone_admin_user_id_fkey FOREIGN KEY (user_id) REFERENCES "user"(id) ON DELETE CASCADE,
		ADD CONSTRAINT zone_admin_zone_id_fkey FOREIGN KEY (zone_id) REFERENCES zone(id) ON DELETE CASCADE;
	');
}
