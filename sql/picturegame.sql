-- Required tables for the PictureGame extension
CREATE TABLE /*_*/picturegame_images (
  `id` int(10) unsigned NOT NULL PRIMARY KEY auto_increment,
  -- Both of these were originally varchar(64)
  `img1` varchar(255) NOT NULL default '',
  `img2` varchar(255) NOT NULL default '',
  -- old version:
  --`flag` enum('NONE','PROTECT','FLAGGED') NOT NULL default 'NONE',
  `flag` tinyint(2) NOT NULL default '0',
  -- Originally varchar(64)
  `title` varchar(255) NOT NULL default '',
  `img1_caption` varchar(255) NOT NULL default '',
  `img2_caption` varchar(255) NOT NULL default '',
  `actor` bigint unsigned NOT NULL,
  `img0_votes` int(10) unsigned NOT NULL default '0',
  `img1_votes` int(10) unsigned NOT NULL default '0',
  `heat` double NOT NULL default '0',
  `pg_date` datetime default NULL,
  `comment` varchar(255) default ''
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/actor ON /*_*/picturegame_images (actor);

CREATE TABLE /*_*/picturegame_votes (
  `picid` int(10) unsigned NOT NULL default '0',
  `actor` bigint unsigned NOT NULL,
  `imgpicked` int(1) unsigned NOT NULL default '0',
  `id` int(10) unsigned NOT NULL PRIMARY KEY auto_increment,
  `vote_date` datetime default NULL
) /*$wgDBTableOptions*/;

CREATE INDEX /*i*/picturegame_actor ON /*_*/picturegame_votes (actor);
CREATE INDEX /*i*/picturegame_pic_id ON /*_*/picturegame_votes (picid);
