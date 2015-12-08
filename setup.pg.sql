CREATE TABLE caps_cache (
    ts timestamp without time zone DEFAULT now(),
    uts bigint,
    nick character varying(24),
    ircuser character varying(24),
    irchost character varying(48),
    command character varying(12),
    args character varying(254),
    message character varying(512)
);

CREATE TABLE conf_reload (
    reload_uts bigint
);

CREATE TABLE func_reloads (
    function character varying(50) NOT NULL,
    reload_uts bigint
);

CREATE TABLE karma_cache (
    channel character varying(48) NOT NULL,
    nick character varying(24) NOT NULL,
    thing character varying(512) NOT NULL,
    up integer,
    down integer
);

CREATE TABLE keyval (
    key character varying(40),
    value character varying(512)
);

CREATE TABLE log (
    ts timestamp without time zone DEFAULT now(),
    uts bigint,
    nick character varying(24),
    ircuser character varying(24),
    irchost character varying(48),
    command character varying(12),
    args character varying(254),
    message character varying(512)
);

CREATE TABLE phone_numbers (
    phone_number character(10),
    last_send_uts bigint,
    last_from_chan character varying(40),
    intro_sent boolean DEFAULT false,
    blocked boolean DEFAULT false
);

CREATE TABLE phonebook (
    nick character varying(30) NOT NULL,
    phone_number character(10)
);

CREATE TABLE proc_funcs (
    proc character varying(10) NOT NULL,
    func character varying(30)
);

CREATE TABLE proc_reloads (
    proc character varying(10) NOT NULL,
    do_reload boolean DEFAULT false
);

CREATE SEQUENCE quotedb_id_seq
    START WITH 1
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

CREATE TABLE quotedb (
    id bigint DEFAULT nextval('quotedb_id_seq'::regclass) NOT NULL,
    quote character varying(8192) NOT NULL,
    set_by character varying(45) NOT NULL,
    set_time timestamp without time zone DEFAULT now() NOT NULL,
    channel character varying(32)
);

CREATE TABLE quotedb_deleted (
    id bigint,
    quote character varying(8192),
    set_by character varying(45),
    set_time timestamp without time zone,
    channel character varying(32)
);

CREATE TABLE sms (
    uts bigint,
    message_sid character(34) NOT NULL,
    from_number character(10),
    message character varying(1600),
    posted boolean DEFAULT false,
    is_mms boolean DEFAULT false,
    dest_chan character varying(40)
);

CREATE TABLE statcache_firstuse (
    channel character varying(30) NOT NULL,
    nick character varying(30) NOT NULL,
    uts bigint
);

CREATE TABLE statcache_lines (
    channel character varying(30) NOT NULL,
    nick character varying(30) NOT NULL,
    lines bigint
);

CREATE TABLE statcache_misc (
    channel character varying(30) NOT NULL,
    stat_name character varying(30) NOT NULL,
    val bigint
);

CREATE TABLE statcache_timeprofile (
    channel character varying(30) NOT NULL,
    nick character varying(30) NOT NULL,
    d_mon bigint DEFAULT 0,
    d_tue bigint DEFAULT 0,
    d_wed bigint DEFAULT 0,
    d_thu bigint DEFAULT 0,
    d_fri bigint DEFAULT 0,
    d_sat bigint DEFAULT 0,
    d_sun bigint DEFAULT 0,
    h_0 bigint DEFAULT 0,
    h_1 bigint DEFAULT 0,
    h_2 bigint DEFAULT 0,
    h_3 bigint DEFAULT 0,
    h_4 bigint DEFAULT 0,
    h_5 bigint DEFAULT 0,
    h_6 bigint DEFAULT 0,
    h_7 bigint DEFAULT 0,
    h_8 bigint DEFAULT 0,
    h_9 bigint DEFAULT 0,
    h_10 bigint DEFAULT 0,
    h_11 bigint DEFAULT 0,
    h_12 bigint DEFAULT 0,
    h_13 bigint DEFAULT 0,
    h_14 bigint DEFAULT 0,
    h_15 bigint DEFAULT 0,
    h_16 bigint DEFAULT 0,
    h_17 bigint DEFAULT 0,
    h_18 bigint DEFAULT 0,
    h_19 bigint DEFAULT 0,
    h_20 bigint DEFAULT 0,
    h_21 bigint DEFAULT 0,
    h_22 bigint DEFAULT 0,
    h_23 bigint DEFAULT 0
);

CREATE TABLE statcache_words (
    channel character varying(30),
    nick character varying(30),
    word text,
    wc bigint
);

CREATE SEQUENCE topic_tid_seq
    START WITH 3596
    INCREMENT BY 1
    NO MINVALUE
    NO MAXVALUE
    CACHE 1;

CREATE TABLE topic (
    tid numeric(20,0) DEFAULT nextval('topic_tid_seq'::regclass) NOT NULL,
    uts bigint,
    topic text,
    by_nick character varying(32),
    channel character varying(32)
);

ALTER TABLE ONLY func_reloads
    ADD CONSTRAINT func_reloads_pkey PRIMARY KEY (function);

ALTER TABLE ONLY karma_cache
    ADD CONSTRAINT karma_cache_pkey PRIMARY KEY (channel, nick, thing);

ALTER TABLE ONLY phonebook
    ADD CONSTRAINT phonebook_pkey PRIMARY KEY (nick);

ALTER TABLE ONLY proc_funcs
    ADD CONSTRAINT proc_funcs_pkey PRIMARY KEY (proc);

ALTER TABLE ONLY proc_reloads
    ADD CONSTRAINT proc_reloads_pkey PRIMARY KEY (proc);

ALTER TABLE ONLY quotedb
    ADD CONSTRAINT quotedb_pkey PRIMARY KEY (id);

ALTER TABLE ONLY sms
    ADD CONSTRAINT sms_pkey PRIMARY KEY (message_sid);

ALTER TABLE ONLY statcache_firstuse
    ADD CONSTRAINT statcache_firstuse_pkey PRIMARY KEY (channel, nick);

ALTER TABLE ONLY statcache_lines
    ADD CONSTRAINT statcache_lines_pkey PRIMARY KEY (channel, nick);

ALTER TABLE ONLY statcache_misc
    ADD CONSTRAINT statcache_misc_pkey PRIMARY KEY (channel, stat_name);

ALTER TABLE ONLY statcache_timeprofile
    ADD CONSTRAINT statcache_timeprofile_pkey PRIMARY KEY (channel, nick);

ALTER TABLE ONLY topic
    ADD CONSTRAINT topic_tid_pkey PRIMARY KEY (tid);

CREATE INDEX log_args ON log USING btree (args);
CREATE INDEX log_nick ON log USING btree (nick);
CREATE INDEX log_uts ON log USING btree (uts);

ALTER TABLE log CLUSTER ON log_uts;

CREATE INDEX statcache_words_idx ON statcache_words USING btree (channel, nick);

CREATE UNIQUE INDEX topic_tid ON topic USING btree (tid);

CREATE OR REPLACE FUNCTION genl() RETURNS text as $$
DECLARE
	chars text[] := '{0,1,2,3,4,5,6,7,8,9,a,b,c,d,e,f,g,h,i,j,k,l,m,n,o,p,q,r,s,t,u,v,w,x,y,z,A,B,C,D,E,F,G,H,I,J,K,L,M,N,O,P,Q,R,S,T,U,V,W,X,Y,Z}';
	ret text := '';
	i integer := 0;
	done bool := FALSE;
BEGIN
	WHILE NOT done LOOP
		FOR i in 1..5 LOOP
			ret := ret || chars[1+random()*(array_length(chars, 1)-1)];
		END LOOP;
		done := NOT exists(SELECT 1 FROM shorten WHERE l=ret);
	END LOOP;
	RETURN ret;
END;
$$ LANGUAGE plpgsql;

CREATE TABLE shorten (
	l char(5) PRIMARY KEY DEFAULT genl(),
	url text
);
