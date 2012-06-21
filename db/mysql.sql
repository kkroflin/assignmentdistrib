CREATE TABLE `mdl_assignmentdistrib` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `course` int(10) unsigned NOT NULL default '0',
  `name` varchar(255) NOT NULL default '',
  `description` text NOT NULL,
  `descriptionformat` tinyint(4) unsigned NOT NULL default '0',
  `worktype` tinyint(4) unsigned NOT NULL default '0',
  `distributiontype` tinyint(4) unsigned NOT NULL default '0',
  `assignmenttype` tinyint(4) NOT NULL default '0',
  `allowgroupmarket` int(1) unsigned NOT NULL default '0',
  `allowstudentchange` int(1) unsigned NOT NULL default '0',
  `preventlate` tinyint(2) unsigned NOT NULL default '0',
  `timedue` int(10) unsigned NOT NULL default '0',
  `timeavailable` int(10) unsigned NOT NULL default '0',
  `leadercangrade` int(1) unsigned NOT NULL default '1',
  `suggestable` int(1) unsigned NOT NULL default '0',
  `grade` int(10) NOT NULL default '0',
  `timemodified` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `course` (`course`)
) COMMENT='Defines special assignments';

CREATE TABLE `mdl_assignmentdistrib_assignments` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `assignmentdistribid` int(10) unsigned NOT NULL default '0',
  `createdbyuserid` int(10) unsigned NOT NULL default '0',
  `name` varchar(255) NOT NULL default '',
  `description` text NOT NULL,
  `descriptionformat` tinyint(4) unsigned NOT NULL default '0',
  `groupstudentmin` int(10) unsigned NOT NULL default '1',
  `groupstudentmax` int(10) unsigned NOT NULL default '1',
  `available` int(1) unsigned NOT NULL default '1',
  `approved` int(1) unsigned NOT NULL default '1',
  `maxnumberofrepeats` int(10) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `assignmentdistribid` (`assignmentdistribid`)
) COMMENT='Defines assignment';

CREATE TABLE `mdl_assignmentdistrib_groups` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `name` varchar(255) NOT NULL default '',
  `leaderuserid` int(10) unsigned NOT NULL default '0',
  `grade` int(11) NOT NULL default '-1',
  `gradedbyuserid` int(10) unsigned NOT NULL default '0',
  `timegraded` int(10) unsigned NOT NULL default '0',
  `comment` text NOT NULL,
  `commentformat` tinyint(4) NOT NULL default '0',
  `allowjoin` int(1) unsigned NOT NULL default '0',
  PRIMARY KEY  (`id`),
  KEY `leaderuserid` (`leaderuserid`)
) COMMENT='Defines assignment groups';

CREATE TABLE `mdl_assignmentdistrib_submissions` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `assignmentdistrib_assignments_id` int(10) unsigned NOT NULL default '0',
  `userid` int(10) unsigned NOT NULL default '0',
  `assignmentdistrib_groups_id` int(10) default NULL,
  `timecreated` int(10) unsigned NOT NULL default '0',
  `timemodified` int(10) unsigned NOT NULL default '0',
  `grade` int(11) NOT NULL default '-1',
  `gradedbyuserid` int(10) unsigned NOT NULL default '0',
  `timegraded` int(10) unsigned NOT NULL default '0',
  `comment` text NOT NULL,
  `commentformat` tinyint(4) unsigned NOT NULL default '0',
  `assignmenttype` tinyint(4) unsigned NOT NULL default '0',
  `var1` text NOT NULL,
  `var2` text NOT NULL,
  PRIMARY KEY  (`id`),
  UNIQUE KEY `userid_assignmentid` (`userid`,`assignmentdistrib_assignments_id`),
  KEY `assignmentdistrib_assignments_id` (`assignmentdistrib_assignments_id`),
  KEY `timemodified` (`timemodified`),
  KEY `userid` (`userid`)
) CHARSET=utf8 COMMENT='Submissions';


CREATE TABLE `mdl_assignmentdistrib_penalties` (
  `id` int(10) unsigned NOT NULL auto_increment,
  `assignmentdistrib_id` int(10) unsigned NOT NULL default '0',
  `time` int(10) unsigned NOT NULL default '0',
  `penalty_grade` int(11) NOT NULL default '0',
  PRIMARY KEY(`id`),
  KEY `assignmentdistrib_id` (`assignmentdistrib_id`)
) CHARSET=utf8 COMMENT='Defines penalties for late submissions';
