-- **********************************************************
-- *                                                        *
-- * IMPORTANT NOTE                                         *
-- *                                                        *
-- * Do not import this file manually but use the TYPOlight *
-- * install tool to create and maintain database tables!   *
-- *                                                        *
-- **********************************************************

-- 
-- Table `tl_adventure`
-- 

CREATE TABLE `tl_adventure` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `tstamp` int(10) unsigned NOT NULL default '0',
  `title` varchar(255) NOT NULL default '',
  `description` text NULL,
  `firstbroadcast` int(10) unsigned NOT NULL default '0',
  `lastbroadcast` int(10) unsigned NOT NULL default '0',
  `numbering` varchar(5) NOT NULL default '',
  `alias` varchar(32) NOT NULL default '',
  PRIMARY KEY  (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- 
-- Table `tl_broadcast`
-- 

CREATE TABLE `tl_broadcast` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `tstamp` int(10) unsigned NOT NULL default '0',
  `sorting` int(10) unsigned NOT NULL default '0',
  `title` varchar(255) NOT NULL default '',
  `date` varchar(32) NOT NULL default '',
  `hour` smallint(5) unsigned NOT NULL default '1',
  `pid` int(10) unsigned NOT NULL default '0',
  `chapter` smallint(5) unsigned NOT NULL default '0',
  `story` char(1) NOT NULL default '',
  `files` blob NULL,
  `special` char(1) NOT NULL default '',
  `length` varchar(6) NOT NULL default '00:00',
  `complete` char(1) NOT NULL default '',
  `providers` blob NULL,
  `frequency` int(10) unsigned NOT NULL default '44100',
  `samplerate` int(10) unsigned NOT NULL default '128',
  PRIMARY KEY  (`id`),
  KEY `pid` (`pid`),
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- 
-- Table `tl_broadcast_song`
-- 

CREATE TABLE `tl_broadcast_song` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `pid` int(10) unsigned NOT NULL default '0',
  `tstamp` int(10) unsigned NOT NULL default '0',
  `sorting` int(10) unsigned NOT NULL default '0',
  `title` varchar(255) NOT NULL default '',
  `artist` varchar(255) NOT NULL default '',
  `album` varchar(255) NOT NULL default '',
  `year` varchar(4) NOT NULL default '',
  `labelcode` varchar(100) NOT NULL default '',
  `genres` blob NULL,
  `songtype` int(10) unsigned NOT NULL default '0',
  `length` varchar(6) NOT NULL default '00:00',
  `composer` varchar(255) NOT NULL default '',
  `sequence` smallint(5) unsigned NOT NULL default '1',
  PRIMARY KEY  (`id`),
  KEY `pid` (`pid`),
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- 
-- Table `tl_broadcast_provider`
-- 

CREATE TABLE `tl_broadcast_provider` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `tstamp` int(10) unsigned NOT NULL default '0',
  `sorting` int(10) unsigned NOT NULL default '0',
  `initials` varchar(3) NOT NULL default '',
  `email` varchar(255) NOT NULL default '',
  `homepage` varchar(255) NOT NULL default '',
  `name` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`id`),
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- 
-- Table `tl_song_type`
-- 

CREATE TABLE `tl_song_type` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `tstamp` int(10) unsigned NOT NULL default '0',
  `sorting` int(10) unsigned NOT NULL default '0',
  `songtype` varchar(255) NOT NULL default '',
  PRIMARY KEY  (`id`),
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- 
-- Table `tl_broadcast_file`
-- 

CREATE TABLE `tl_broadcast_file` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `pid` int(10) unsigned NOT NULL default '0',
  `tstamp` int(10) unsigned NOT NULL default '0',
  `sorting` int(10) unsigned NOT NULL default '0',
  `filename` varchar(50) NOT NULL default '',
  PRIMARY KEY  (`id`),
  KEY `pid` (`pid`),
) ENGINE=MyISAM DEFAULT CHARSET=utf8;


-- 
-- Table `tl_genre`
-- 

CREATE TABLE `tl_genre` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `tstamp` int(10) unsigned NOT NULL default '0',
  `name` varchar(30) NOT NULL default '',
  PRIMARY KEY  (`id`),
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- 
-- Table `tl_module`
-- 

CREATE TABLE `tl_module` (
  `adventure_source` int(10) unsigned NOT NULL default '0',
  `jumpTo_adventure` int(10) unsigned NOT NULL default '0',
  `jumpTo_broadcast` int(10) unsigned NOT NULL default '0',
  `admingroups` blob NULL,
  `jumpTo_song` int(10) unsigned NOT NULL default '0',
  `jumpTo_search` int(10) unsigned NOT NULL default '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- 
-- Table `tl_content`
-- 

CREATE TABLE `tl_content` (
  `jumpTo_song` int(10) unsigned NOT NULL default '0',
  `special_type` int(10) unsigned NOT NULL default '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- 
-- Table `tl_cover`
-- 

CREATE TABLE `tl_cover` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `tstamp` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
) ENGINE=MyISAM DEFAULT CHARSET=utf8;

-- 
-- Table `tl_song_cover`
-- 

CREATE TABLE `tl_song_cover` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `tstamp` int(10) unsigned NOT NULL default '0',
  `song` int(10) unsigned NOT NULL default '0',
  `cover` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
) ENGINE=MyISAM DEFAULT CHARSET=utf8;
