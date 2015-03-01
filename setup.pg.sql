-- Postgres setup file

CREATE DATABASE extraserv;
CREATE EXTENSION pgcrypto;

CREATE TABLE blocked_numbers (
	phone_number CHAR(10)
);

CREATE TABLE chan_register (
	channel VARCHAR(48) PRIMARY KEY,
	reg_uts BIGINT,
	owner_ircuser VARCHAR(24),
	stickymodes BOOLEAN DEFAULT FALSE,
	stickylists BOOLEAN DEFAULT FALSE,
	list_flags VARCHAR(7),
	mode_flags VARCHAR(24),
	mode_k VARCHAR(72),
	mode_l VARCHAR(6)
);

CREATE TABLE chan_stickylists (
	channel VARCHAR(48),
	mode_list CHAR(1),
	value VARCHAR(72)
);

CREATE TABLE karma_cache (
	channel VARCHAR(48),
	nick VARCHAR(24),
	thing VARCHAR(512),
	up INTEGER,
	down INTEGER,
	PRIMARY KEY (channel, nick, thing)
);

CREATE TABLE log (
	ts TIMESTAMP WITHOUT TIME ZONE DEFAULT now(),
	uts BIGINT,
	nick VARCHAR(24),
	ircuser VARCHAR(24),
	irchost VARCHAR(48),
	command VARCHAR(12),
	args VARCHAR(254),
	message VARCHAR(512)
);

CREATE TABLE sms (
	uts BIGINT,
	message_sid CHAR(34) PRIMARY KEY,
	from_number CHAR(10),
	message VARCHAR(1600),
	posted BOOLEAN DEFAULT FALSE,
	is_mms BOOLEAN DEFAULT FALSE
);

CREATE TABLE phone_intro_sent (
	phone_number CHAR(10)
);

CREATE TABLE phone_register (
	phone_number CHAR(10) UNIQUE,
	nick VARCHAR(24) UNIQUE,
	default_channel VARCHAR(48),
	gravity_enable BOOLEAN DEFAULT TRUE,
	last_send_uts BIGINT,
	last_from_chan VARCHAR(48),
	sms_enable BOOLEAN DEFAULT TRUE,
	mms_enable BOOLEAN DEFAULT TRUE,
	phonebook_enable BOOLEAN DEFAULT TRUE
);

CREATE TABLE phone_verify (
	phone_number CHAR(10) PRIMARY KEY,
	ircuser VARCHAR(24),
	verification_code VARCHAR(12),
	verification_sent_uts BIGINT,
	verified_uts BIGINT
);

CREATE TABLE user_nick_map (
	ircuser VARCHAR(24) NOT NULL,
	nick VARCHAR(24) PRIMARY KEY
);

CREATE TABLE user_profile (
	id BIGSERIAL PRIMARY KEY,
	ircuser VARCHAR(24), 
	ey VARCHAR(48),
	value TEXT,
	md5sum CHAR(32)
);

CREATE TABLE user_register (
	ircuser VARCHAR(24) PRIMARY KEY,
	password CHAR(60),
	reg_uts BIGINT,
	kill_bad_nicks BOOLEAN DEFAULT TRUE,
	kill_second_user BOOLEAN DEFAULT TRUE
);

