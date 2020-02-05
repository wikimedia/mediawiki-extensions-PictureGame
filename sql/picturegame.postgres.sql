DROP SEQUENCE IF EXISTS picturegame_images_id_seq CASCADE;
CREATE SEQUENCE picturegame_images_id_seq;

CREATE TABLE picturegame_images (
	id INTEGER NOT NULL PRIMARY KEY DEFAULT nextval('picturegame_images_id_seq'),
	img1 TEXT NOT NULL default '',
	img2 TEXT NOT NULL default '',
	flag SMALLINT NOT NULL default 0,
	title TEXT NOT NULL default '',
	img1_caption TEXT NOT NULL default '',
	img2_caption TEXT NOT NULL default '',
	actor INTEGER NOT NULL,
	img0_votes INTEGER NOT NULL default 0,
	img1_votes INTEGER NOT NULL default 0,
	heat DOUBLE PRECISION NOT NULL default '0',
	pg_date TIMESTAMPTZ default NULL,
	comment TEXT default ''
);

ALTER SEQUENCE picturegame_images_id_seq OWNED BY picturegame_images.id;

CREATE INDEX actor_idx ON picturegame_images (actor);

DROP SEQUENCE IF EXISTS picturegame_votes_id_seq CASCADE;
CREATE SEQUENCE picturegame_votes_id_seq;

CREATE TABLE picturegame_votes (
	id INTEGER NOT NULL PRIMARY KEY DEFAULT nextval('picturegame_votes_id_seq'),
	picid INTEGER NOT NULL default 0,
	actor INTEGER NOT NULL,
	imgpicked SMALLINT NOT NULL default 0,
	vote_date TIMESTAMPTZ default NULL
);

ALTER SEQUENCE picturegame_votes_id_seq OWNED BY picturegame_votes.id;

CREATE INDEX picturegame_actor ON picturegame_votes (actor);
CREATE INDEX picturegame_pic_id ON picturegame_votes (picid);
