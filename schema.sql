--
-- PostgreSQL database dump
--

SET statement_timeout = 0;
SET lock_timeout = 0;
SET client_encoding = 'SQL_ASCII';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;

CREATE EXTENSION IF NOT EXISTS plpgsql WITH SCHEMA pg_catalog;
COMMENT ON EXTENSION plpgsql IS 'PL/pgSQL procedural language';

SET search_path = public, pg_catalog;

CREATE TYPE access_level AS ENUM (
    'administrator',
    'operator'
);
CREATE TYPE auth_realm AS ENUM (
    'LDAP',
    'local'
);

SET default_tablespace = '';
SET default_with_oids = false;


CREATE TABLE change (
    id integer NOT NULL,
    changeset_id integer NOT NULL,
    before bytea,
    after bytea
);
CREATE SEQUENCE change_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE change_id_seq OWNED BY change.id;
ALTER TABLE ONLY change ALTER COLUMN id SET DEFAULT nextval('change_id_seq'::regclass);
ALTER TABLE ONLY change ADD CONSTRAINT change_pkey PRIMARY KEY (id);


CREATE TABLE changeset (
    id integer NOT NULL,
    zone_id integer NOT NULL,
    author_id integer NOT NULL,
    change_date timestamp without time zone NOT NULL,
    comment text,
    deleted integer DEFAULT 0 NOT NULL,
    added integer DEFAULT 0 NOT NULL,
    requester_id integer
);
CREATE SEQUENCE changeset_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE changeset_id_seq OWNED BY changeset.id;
ALTER TABLE ONLY changeset ALTER COLUMN id SET DEFAULT nextval('changeset_id_seq'::regclass);
ALTER TABLE ONLY changeset ADD CONSTRAINT changeset_pkey PRIMARY KEY (id);


CREATE TABLE config (
    id integer NOT NULL,
    default_soa_template integer,
    default_ns_template integer
);
ALTER TABLE ONLY config ADD CONSTRAINT config_pkey PRIMARY KEY (id);
INSERT INTO "config" (id) VALUES (1);


CREATE TABLE ns_template (
    id integer NOT NULL,
    name text NOT NULL,
    nameservers text NOT NULL
);
CREATE SEQUENCE ns_template_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE ns_template_id_seq OWNED BY ns_template.id;
ALTER TABLE ONLY ns_template ALTER COLUMN id SET DEFAULT nextval('ns_template_id_seq'::regclass);
ALTER TABLE ONLY ns_template ADD CONSTRAINT ns_template_name_key UNIQUE (name);
ALTER TABLE ONLY ns_template ADD CONSTRAINT ns_template_pkey PRIMARY KEY (id);


CREATE TABLE pending_update (
    id integer NOT NULL,
    zone_id integer NOT NULL,
    author_id integer,
    request_date timestamp without time zone NOT NULL,
    raw_data bytea NOT NULL
);
CREATE SEQUENCE pending_update_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE pending_update_id_seq OWNED BY pending_update.id;
ALTER TABLE ONLY pending_update ALTER COLUMN id SET DEFAULT nextval('pending_update_id_seq'::regclass);
ALTER TABLE ONLY pending_update ADD CONSTRAINT pending_change_pkey PRIMARY KEY (id);


CREATE TABLE soa_template (
    id integer NOT NULL,
    name text NOT NULL,
    primary_ns text NOT NULL,
    contact text NOT NULL,
    refresh integer NOT NULL,
    retry integer NOT NULL,
    expire integer NOT NULL,
    default_ttl integer NOT NULL,
    soa_ttl integer
);
CREATE SEQUENCE soa_template_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE soa_template_id_seq OWNED BY soa_template.id;
ALTER TABLE ONLY soa_template ALTER COLUMN id SET DEFAULT nextval('soa_template_id_seq'::regclass);
ALTER TABLE ONLY soa_template ADD CONSTRAINT soa_template_name_key UNIQUE (name);
ALTER TABLE ONLY soa_template ADD CONSTRAINT soa_template_pkey PRIMARY KEY (id);


CREATE TABLE "user" (
    id integer NOT NULL,
    uid text,
    name text,
    email text,
    auth_realm auth_realm,
    active integer,
    admin integer DEFAULT 0 NOT NULL,
    developer integer DEFAULT 0 NOT NULL,
    csrf_token text
);
CREATE SEQUENCE user_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE user_id_seq OWNED BY "user".id;
ALTER TABLE ONLY "user" ALTER COLUMN id SET DEFAULT nextval('user_id_seq'::regclass);
ALTER TABLE ONLY "user" ADD CONSTRAINT user_pkey PRIMARY KEY (id);
ALTER TABLE ONLY "user" ADD CONSTRAINT user_uid_key UNIQUE (uid);


CREATE TABLE user_alert (
    id integer NOT NULL,
    user_id integer,
    class text,
    content text,
    escaping integer
);
CREATE SEQUENCE user_alert_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE user_alert_id_seq OWNED BY user_alert.id;
ALTER TABLE ONLY user_alert ALTER COLUMN id SET DEFAULT nextval('user_alert_id_seq'::regclass);
ALTER TABLE ONLY user_alert ADD CONSTRAINT user_alert_pkey PRIMARY KEY (id);


CREATE TABLE zone (
    id integer NOT NULL,
    pdns_id text,
    name text,
    serial bigint,
    active boolean DEFAULT true NOT NULL,
    account text
);
CREATE SEQUENCE zone_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;
ALTER SEQUENCE zone_id_seq OWNED BY zone.id;
ALTER TABLE ONLY zone ALTER COLUMN id SET DEFAULT nextval('zone_id_seq'::regclass);
ALTER TABLE ONLY zone ADD CONSTRAINT zone_pdns_id_key UNIQUE (pdns_id);
ALTER TABLE ONLY zone ADD CONSTRAINT zone_pkey PRIMARY KEY (id);


CREATE TABLE zone_access (
    zone_id integer NOT NULL,
    user_id integer NOT NULL,
    level access_level DEFAULT 'administrator'::access_level NOT NULL
);
ALTER TABLE ONLY zone_access ADD CONSTRAINT zone_admin_pkey PRIMARY KEY (zone_id, user_id);


CREATE INDEX fki_user_alert_user_id_fkey ON user_alert USING btree (user_id);


ALTER TABLE ONLY change
    ADD CONSTRAINT change_changeset_id_fkey FOREIGN KEY (changeset_id) REFERENCES changeset(id) ON DELETE CASCADE;

ALTER TABLE ONLY changeset
    ADD CONSTRAINT changeset_author_id_fkey FOREIGN KEY (author_id) REFERENCES "user"(id);

ALTER TABLE ONLY changeset
    ADD CONSTRAINT changeset_requester_id_fkey FOREIGN KEY (requester_id) REFERENCES "user"(id);

ALTER TABLE ONLY changeset
    ADD CONSTRAINT changeset_zone_id_fkey FOREIGN KEY (zone_id) REFERENCES zone(id) ON DELETE CASCADE;

ALTER TABLE ONLY config
    ADD CONSTRAINT config_default_ns_template_fkey FOREIGN KEY (default_ns_template) REFERENCES ns_template(id) ON DELETE SET NULL;

ALTER TABLE ONLY pending_update
    ADD CONSTRAINT pending_change_zone_id_fkey FOREIGN KEY (zone_id) REFERENCES zone(id) ON DELETE CASCADE;

ALTER TABLE ONLY user_alert
    ADD CONSTRAINT user_alert_user_id_fkey FOREIGN KEY (user_id) REFERENCES "user"(id);

ALTER TABLE ONLY zone_access
    ADD CONSTRAINT zone_admin_user_id_fkey FOREIGN KEY (user_id) REFERENCES "user"(id) ON DELETE CASCADE;

ALTER TABLE ONLY zone_access
    ADD CONSTRAINT zone_admin_zone_id_fkey FOREIGN KEY (zone_id) REFERENCES zone(id) ON DELETE CASCADE;
