#
# Table structure for table 'tx_spbettercontact_log'
#
CREATE TABLE tx_spbettercontact_log (
	uid int(11) NOT NULL auto_increment,
	pid int(11) DEFAULT '0' NOT NULL,
	tstamp int(11) DEFAULT '0' NOT NULL,
	crdate int(11) DEFAULT '0' NOT NULL,
	cruser_id int(11) DEFAULT '0' NOT NULL,
	deleted int(11) DEFAULT '0' NOT NULL,
	ip varchar(15) DEFAULT '' NOT NULL,
	agent varchar(255) DEFAULT '' NOT NULL,
	hash varchar(32) DEFAULT '' NOT NULL,
	params text,

	PRIMARY KEY (uid),
	KEY parent (pid)
);